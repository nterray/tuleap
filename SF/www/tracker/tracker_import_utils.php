<?php

//
// Copyright (c) Xerox Corporation, CodeX Team, 2001-2003. All rights reserved
//
// $Id$
//
//
//  Written for CodeX by Marie-Luise Schneider
//

$Language->loadLanguageMsg('tracker/tracker');
$Language->loadLanguageMsg('include/include');

/** parse the first line of the csv file containing all the labels of the fields that are
 * used in the following of the file
 * @param $data (IN): array containing the field labels
 * @param $used_fields (IN): array containing all the fields that are used in this tracker
 * @param $ath (IN): the tracker
 * @param $num_columns (OUT): number of columns in the data array
 * @param $parsed_labels (OUT): array of the form (column_number => field_label) containing
 *                              all the fields parsed from $data
 * @param $predefined_values (OUT): array of the form (column_number => array of field predefined values)
 * @param $aid_column (OUT): the column in the csv file that contains the arifact id (-1 if not given)
 * @param $errors (OUT): string containing explanation what error occurred
 * @return true if parse ok, false if errors occurred
 */ 
function parse_field_names($data,$used_fields,$ath,
			   &$num_columns,&$parsed_labels,&$predefined_values,
			   &$aid_column,&$submitted_by_column,&$submitted_on_column,
			   &$errors) {
    global $Language, $art_field_fact;

  $aid_column = -1;
  $submitted_by_column = -1;
  $submitted_on_column = -1;
  $num_columns = count($data);
  
  for ($c=0; $c < $num_columns; $c++) {
    $field_label = $data[$c];
    if (!array_key_exists($field_label,$used_fields)) {
      $errors .= $Language->getText('tracker_import_utils','field_not_known',array($field_label,$ath->getName()));
      return false;
    }
    
    $field = $used_fields[$field_label];
    if ($field != "") {
      $field_name = $field->getName();
      if ($field_name == "artifact_id") $aid_column = $c; 
      if ($field_name == "submitted_by") $submitted_by_column = $c;
      if ($field_name == "open_date") $submitted_on_column = $c;
    }
    $parsed_labels[$c] = $field_label;

    //get already the predefined values of this field (if applicable)
    if ($field != "" && 
	($field->getDisplayType() == "SB" || $field->getDisplayType() == "MB")) {

      //special case for submitted by
      if ($field_name == "submitted_by") {
	// simply put nothing in predefined values for submitted_by
	// as we accept all logged users, even None for allow-anon trackers
      
	//for all other fields not submitted by
      } else {

	$predef_val = $field->getFieldPredefinedValues($ath->getID());
	$count = db_numrows($predef_val);
	unset($values);
	for ($i=0;$i<$count;$i++) {
	  $values[db_result($predef_val,$i,1)] = db_result($predef_val,$i,0);
	}
	$predefined_values[$c] = $values;
      }
    }
  }
 
  // verify if we have all mandatory fields in the case we have to create an artifact
  if ($aid_column == -1) {

      // TODO: Localize this properly by adding those 4 fields to the artifact table
      // (standard fields) and the artifact field table with a special flag and make sure
      // all tracker scripts handle them properly
      // For now make a big hack!! (see import.php func=showformat)
      $submitted_field = $art_field_fact->getFieldFromName('submitted_by');
      if (strstr($submitted_field->getLabel(),"ubmit")) {
          // Assume English
          $lbl_follow_ups = 'Follow-up Comments';
          $lbl_s_dependent_on = 'Depend on';
          $lbl_add_cc = 'CC List';
          $lbl_cc_comment = 'CC Comment';
      } else {
          // Assume French
          $lbl_follow_ups = 'Commentaires';
          $lbl_is_dependent_on = 'D�pend de';
	  $lbl_add_cc = 'Liste CC';
          $lbl_cc_comment = 'Commentaire CC';
      }        
    
      reset($used_fields);

      while (list($label,$field) = each($used_fields)) {
          //echo $label.",";
          if ($field) {
              $field_name = $field->getName();
              if ($field_name != "artifact_id" &&
                  $field_name != "open_date" &&
                  $field_name != "submitted_by" &&
                  $label != $lbl_follow_ups &&
                  $label != $lbl_is_dependent_on &&
                  $label != $lbl_cc_list &&
                  $label != $lbl_cc_comment &&
                  !$field->isEmptyOk() && !in_array($label,$parsed_labels)) {
              
                  $errors .= $Language->getText('tracker_import_utils','field_mandatory',array($label,$ath->getName())).' ';
                  return false;
              }
          }
      }
  }
  return true;
}



/** check if all the values correspond to predefined values of the corresponding fields
 * @param data (IN + OUT !): for date fields we transform the given format (accepted by util_date_to_unixtime)
 *                           into format "Y-m-d"
 * @param insert: if we check values for inserting this artifact data. If so, we accept
 *                 submitted on and submitted by as "" and insert it later on 
 * @param from_update: take into account special case where column artifact_id is specified but
 *                      for this concrete artifact no aid is given
 */
function check_values($row,&$data,$used_fields,$parsed_labels,$predefined_values,&$errors,$insert,$from_update=false) {
  global $ath,$Language,$art_field_fact;
  for ($c=0; $c < count($parsed_labels); $c++) {
    $label = $parsed_labels[$c];
    $val = $data[$c];
    $field = $used_fields[$label];
    if ($field != "") $field_name = $field->getName();

    // check if val in predefined vals (if applicable)
    $predef_vals = $predefined_values[$c];
    if ($predef_vals) {
      if ($field->getDisplayType() == "MB") {
	$val_arr = explode(",",$val);
	while (list(,$name) = each($val_arr)) {
	  if (!array_key_exists($name,$predef_vals) && $name != $Language->getText('global','none')) {
	    $errors .= $Language->getText('tracker_import_utils','not_a_predefined_value',array($row+1,implode(",",$data),$name,$label,implode(",",array_keys($predef_vals))));
	    return false;
	  }
	}
      } else {
	if (!array_key_exists($val,$predef_vals) && $val != $Language->getText('global','none')) {
	  if (($field_name == 'severity') &&
	      (strcasecmp($val,'1') == 0 || strcasecmp($val,'5') == 0 || strcasecmp($val,9) == 0)) {
	    //accept simple ints for Severity fields instead of 1 - Ordinary,5 - Major,9 - Critical
	    //accept simple ints for Priority fields instead of 1 - Lowest,5 - Medium,9 - Highest
	  } else if ($field_name == 'submitted_by' && 
		     (($val == $Language->getText('global','none') && $ath->allowsAnon()) ||
		     $val == "" ||
		     user_getemail_from_unix($val) != $Language->getText('include_user','not_found'))) {
	    //accept anonymous user, use importing user as 'submitted by', or simply make sure that user is a known user
	  } else {
	    $errors .= $Language->getText('tracker_import_utils','not_a_predefined_value',array($row+1,implode(",",$data),$val,$label,implode(",",array_keys($predef_vals))));
	    return false;
	  }
	}
      }
    }
    
    // check whether we specify None for a field which is mandatory
    if ($field != "" && !$field->isEmptyOk() &&
	$field_name != "artifact_id") {
      if ($field_name == "submitted_by" ||
	   $field_name == "open_date") {
	//submitted on and submitted by are accepted as "" on inserts and
	//we put time() importing user as default
      } else {
	
	if ($field->isMultiSelectBox()) {
	  $is_empty = (implode(",",$val)=="100");
	} else {
	  $is_empty = ( ($field->isSelectBox()) ? ($val==$Language->getText('global','none')) : ($val==''));
	}

	if ($is_empty) {
	  $errors .= $Language->getText('tracker_import_utils','field_mandatory_and_current',array($row+1,implode(",",$data),$label,$ath->getName(),$val));
	  return false;
	}
      }
    }

    // for date fields: check format
    if ($field != "" && $field->isDateField()) {
      if ($field_name == "open_date" && $val == "") {
	//is ok.
      } else {
	
	if ($val == "-" || $val == "") {
	  //ok. transform it by hand into 0 before updating db
	  $data[$c] = "";
	} else {
	  list($unix_time,$ok) = util_importdatefmt_to_unixtime($val);
	  if (!ok) {
	    $errors .= $Language->getText('tracker_import_utils','incorrect_date',array($row+1,implode(",",$data),$val));
	  }
	  $date = format_date("Y-m-d",$unix_time);
	  $data[$c] = $date;
	}
      }
    }

  }

  // if we come from update case ( column artifact_id is specified but for this concrete artifact no aid is given)
  // we have to check whether all mandatory fields are specified and not empty
  if ($from_update) {
    while (list($label,$field) = each($used_fields)) {
      if ($field != "") $field_name = $field->getName();

      // TODO: Localize this properly by adding those 4 fields to the artifact table
      // (standard fields) and the artifact field table with a special flag and make sure
      // all tracker scripts handle them properly
      // For now make a big hack!! (see import.php func=showformat)
      $submitted_field = $art_field_fact->getFieldFromName('submitted_by');
      if (strstr($submitted_field->getLabel(),"ubmit")) {
          // Assume English
          $lbl_follow_ups = 'Follow-up Comments';
          $lbl_s_dependent_on = 'Depend on';
          $lbl_add_cc = 'CC List';
          $lbl_cc_comment = 'CC Comment';
      } else {
          // Assume French
          $lbl_follow_ups = 'Commentaires';
          $lbl_is_dependent_on = 'D�pend de';
	  $lbl_add_cc = 'Liste CC';
          $lbl_cc_comment = 'Commentaire CC';
      }

      if ($field_name != "artifact_id" &&
	  $field_name != "open_date" &&
	  $field_name != "submitted_by" &&
	  $label != $lbl_follow_ups &&
	  $label != $lbl_is_dependent_on &&
	  $label != $lbl_cc_list &&
	  $label != $lbl_cc_comment &&
	  !$field->isEmptyOk() && !in_array($label,$parsed_labels)) {

	$errors .= $Language->getText('tracker_import_utils','field_mandatory_and_line',array($row+1,implode(",",$data),$label,$ath->getName()));
	  return false;
      } 
    }
  }
  
  return true;
}


/**
 * @param $from_update: take into account special case where column artifact_id is specified but
 *                      for this concrete artifact no aid is given
 */
function check_insert_artifact($row,&$data,$used_fields,$parsed_labels,$predefined_values,&$errors,$from_update=false) {
  global $art_field_fact,$ath,$Language;
  // first make sure this isn't double-submitted
  
  //$field = $used_fields["Summary"];
  $summary_field = $art_field_fact->getFieldFromName('summary');
  $summary_label = $summary_field->getLabel();
  $summary_col = array_search($summary_label,$parsed_labels);

  $submitted_by_field = $art_field_fact->getFieldFromName('submitted_by');
  $submitted_by_label = $submitted_by_field->getLabel();
  $submitted_by_col = array_search($submitted_by_label,$parsed_labels);
  $summary = htmlspecialchars($data[$summary_col]);
  if ($submitted_by_col !== false) {
    $sub_user_name = $data[$submitted_by_col];
    //$sub_user_ids = $predefined_values[$submitted_by_col];
     $res = user_get_result_set_from_unix($sub_user_name);
     $sub_user_id = db_result($res,0,'user_id');
  } else {
    get_import_user($sub_user_id,$sub_user_name);
  }
  
  
  if ( $summary_field && $summary_field->isUsed() ) {
    $res=db_query("SELECT * FROM artifact WHERE group_artifact_id = ".$ath->getID().
		  " AND submitted_by=$sub_user_id AND summary=\"$summary\"");
    if ($res && db_numrows($res) > 0) {
      $errors .= $Language->getText('tracker_import_utils','already_submitted',array($row+1,implode(",",$data),$sub_user_name,$summary));
      return false;           
    }
  }
  
  return check_values($row,$data,$used_fields,$parsed_labels,$predefined_values,$errors,true,$from_update);
}



/** check if all the values correspond to predefined values of the corresponding fields */
function check_update_artifact($row,&$data,$aid,$used_fields,$parsed_labels,$predefined_values,&$errors) {
  global $ath,$Language;
  
  $sql = "SELECT artifact_id FROM artifact WHERE artifact_id = $aid and group_artifact_id = ".$ath->getID();
  $result = db_query($sql);
  if (db_numrows($result) == 0) {
    $errors .= $Language->getText('tracker_import_utils','art_not_exists',array($row+1,implode(",",$data),$aid,$ath->getName()));
    return false;
  }
  
  return check_values($row,$data,$used_fields,$parsed_labels,$predefined_values,$errors,false);
}



/**
 * create the html output to visualize what has been parsed
 * @param $used_fields: array containing all the fields that are used in this tracker
 * @param $parsed_labels: array of the form (column_number => field_label) containing
 *                        all the fields parsed from $data
 * @param $artifacts_data: array containing the records for each artifact to be imported
 * @param $aid_column: the column in the csv file that contains the arifact id (-1 if not given)
 * @param $submitted_by_column: the column in the csv file that contains the Submitter (-1 if not given)
 * @param $submitted_on_column: the column in the csv file that contains the artifact creation date (-1 if not given)
 */
function show_parse_results($used_fields,$parsed_labels,$artifacts_data,$aid_column,$submitted_by_column,$submitted_on_column,$group_id) {
  global $art_field_fact,$ath,$PHP_SELF,$sys_datefmt,$Language;
  get_import_user($sub_user_id,$sub_user_name);
  $sub_on = format_date("Y-m-d",time());

  
  //add submitted_by and submitted_on columns only when 
  //artifact_id is not given otherwise the artifacts should
  //only be updated and we don't need to touch sub_on and sub_by
  if ($aid_column == -1 && $submitted_by_column == -1) {
      $new_sub_by_col = count($parsed_labels);
    $submitted_by_field = $art_field_fact->getFieldFromName('submitted_by');
    $parsed_labels[] = $submitted_by_field->getLabel();
  }

  if ($aid_column == -1 && $submitted_on_column == -1) {
    $new_sub_on_col = count($parsed_labels);
    $open_date_field = $art_field_fact->getFieldFromName('open_date');
    $parsed_labels[] = $open_date_field->getLabel();
  }

  echo '
        <FORM NAME="acceptimportdata" action="'.$PHP_SELF.'" method="POST" enctype="multipart/form-data">
        <p align="left"><INPUT TYPE="SUBMIT" NAME="submit" VALUE="'.$Language->getText('tracker_import_admin','import').'"></p>';


  echo html_build_list_table_top ($parsed_labels);

  
  for ($i=0; $i < count($artifacts_data) ; $i++) {

    $data = $artifacts_data[$i];
    if ($aid_column != -1) $aid = $data[$aid_column];

    echo '<TR class="'.util_get_alt_row_color($i).'">'."\n";
    
    for ($c=0; $c < count($parsed_labels); $c++) {
      
      $value = $data[$c];
      $width = ' class="small"';

      if ($value != "") {

	  // TODO: Localize this properly by adding those 4 fields to the artifact table
	  // (standard fields) and the artifact field table with a special flag and make sure
	  // all tracker scripts handle them properly
	  // For now make a big hack!! (see import.php func=showformat)
	  $submitted_field = $art_field_fact->getFieldFromName('submitted_by');
	  if (strstr($submitted_field->getLabel(),"ubmit")) {
	      // Assume English
	      $lbl_follow_ups = 'Follow-up Comments';
	  } else {
	      // Assume French
	      $lbl_follow_ups = 'Commentaires';
	  }        

    	//FOLLOW_UP COMMENTS
	if ($parsed_labels[$c] == $lbl_follow_ups) {
	  unset($parsed_details);
	  unset($parse_error);
	  if (parse_details($data[$c],$parsed_details,$parse_error,true)) {
	    if (count($parsed_details) > 0) {
	      echo '<TD $width valign="top"><TABLE>';
	      echo '<TR class ="boxtable"><TD class="boxtitle">'.$Language->getText('tracker_import_utils','date').'</TD><TD class="boxtitle">'.$Language->getText('global','by').'</TD><TD class="boxtitle">'.$Language->getText('tracker_import_utils','type').'</TD><TD class="boxtitle">'.$Language->getText('tracker_import_utils','comment').'</TD></TR>';
	      for ($d=0; $d < count($parsed_details); $d++) {
		$arr = $parsed_details[$d];
		echo '<TR class="'.util_get_alt_row_color($d).'">';
		echo "<TD $width>".$arr['date']."</TD><TD $width>".$arr['by']."</TD><TD $width>".$arr['type']."</TD><TD $width>".$arr['comment']."</TD>";
		echo "</TR>\n";
	      }
	      echo "</TABLE></TD>";
	    } else {
	      echo "<TD $width align=\"center\">-</TD>\n";
	    }
	  } else {
	    echo "<TD $width><I>".$Language->getText('tracker_import_utils','comment_parse_error',$parse_error)."</I></TD>\n";
	  }
	  
	  //DEFAULT
	} else {
	  echo "<TD $width valign=\"top\">$value</TD>\n";
	}


      } else {
	
	$submitted_by_field = $art_field_fact->getFieldFromName('submitted_by');
	$open_date_field = $art_field_fact->getFieldFromName('open_date');
	$aid_field = $art_field_fact->getFieldFromName('artifact_id');

	//SUBMITTED_ON
	if ($parsed_labels[$c] == $open_date_field->getLabel()) {
	  //if insert show default value
	  if ($aid_column == -1 || $aid == "") echo "<TD $width valign=\"top\"><I>$sub_on</I></TD>\n";
	  else echo "<TD $width valign=\"top\"><I>".$Language->getText('tracker_import_utils','unchanged')."</I></TD>\n";

	  //SUBMITTED_BY
	} else if ($parsed_labels[$c] == $submitted_by_field->getLabel()) {
	  if ($aid_column == -1 || $aid == "") echo "<TD $width valign=\"top\"><I>$sub_user_name</I></TD>\n";
	  else echo "<TD $width valign=\"top\"><I>".$Language->getText('tracker_import_utils','unchanged')."</I></TD>\n";

	  //ARTIFACT_ID
	} else if ($parsed_labels[$c] == $aid_field->getLabel()) {
	  echo "<TD $width valign=\"top\"><I>".$Language->getText('tracker_import_utils','new')."</I></TD>\n";

	  //DEFAULT
	} else {
	  echo "<TD $width  valign=\"top\" align=\"center\">-</TD>\n";
	}
      }
    }
    echo "</tr>\n";
  }
  
  echo "</TABLE>\n";
  
  echo '
        <INPUT TYPE="HIDDEN" NAME="atid" VALUE="'.$ath->getID().'">
        <INPUT TYPE="HIDDEN" NAME="group_id" VALUE="'.$group_id.'">
        <INPUT TYPE="HIDDEN" NAME="func" VALUE="import">
        <INPUT TYPE="HIDDEN" NAME="mode" VALUE="import">
        <INPUT TYPE="HIDDEN" NAME="aid_column" VALUE="'.$aid_column.'">
        <INPUT TYPE="HIDDEN" NAME="count_artifacts" VALUE="'.count($artifacts_data).'">';
  
  while (list(,$label) = each($parsed_labels)) {
    echo '
        <INPUT TYPE="HIDDEN" NAME="parsed_labels[]" VALUE="'.$label.'">';
  }
  

  for ($i=0; $i < count($artifacts_data); $i++) {
    $data = $artifacts_data[$i];
    for ($c=0; $c < count($data); $c++) {
      echo '
        <INPUT TYPE="HIDDEN" NAME="artifacts_data_'.$i.'_'.$c.'" VALUE="'.htmlspecialchars($data[$c]).'">';
    }
  }
  
  echo '
        </FORM>';
  
}



/** parse a file in csv format containing artifacts to be imported into the db
 * @param $csv_filename (IN): the complete file name of the cvs file to be parsed
 * @param $atid (IN): the tracker id
 * @param $group_id (IN): 
 * @param $is_tmp (IN): true if cvs_file is only temporary file and we want to unlink it 
 *                      after parsing
 * @param $used_fields (OUT): the fields used in tracker $atid
 *                            array of the form (label => field)
 * @param $parsed_labels (OUT): the field labels parsed in the csv file
 *                              array of the form (column_number => field_label)
 * @param $artifacts (OUT): the artifacts with their field values parsed from the csv file
 * @param $errors (OUT): string containing explanation what error occurred
 * @return true if parse ok, false if errors occurred
 */
function parse($csv_filename,$group_id,$is_tmp,
	       &$used_fields,&$parsed_labels,&$artifacts_data,
	       &$aid_column,&$submitted_by_column,&$submitted_on_column,
	       &$number_inserts,&$number_updates,
	       &$errors) {
  global $ath,$Language;

  $number_inserts = 0;
  $number_updates = 0;
  
  //avoid that lines with a length > 1000 will be truncated by fgetcsv
  $length = 1000;
  $array = file($csv_filename);
  for($i=0;$i<count($array);$i++) {
    if ($length < strlen($array[$i])) {
      $length = strlen($array[$i]);
    }
  }
  $length++;
  //unset($array);


  $used_fields = getUsedFields();
  
  $csv_file = fopen($csv_filename, "r");
  $row = 0;
  
  while ($data = fgetcsv($csv_file, $length, ",")) {
    // do the real parsing here
    
    //parse the first line with all the field names
    if ($row == 0) {
      $ok = parse_field_names($data,$used_fields,$ath,$num_columns,$parsed_labels,$predefined_values,
			      $aid_column,$submitted_by_column,$submitted_on_column,
			      $errors);
      
      if (!$ok) return false;
      
      //parse artifact values
    } else {
      
      //verify whether this row contains enough values
      $num = count($data);
      if ($num != $num_columns) { 
	$errors .= $Language->getText('tracker_import_utils','column_mismatch',$row+1,implode(",",$data),$num,$num_columns);
	return FALSE;
      }
      
      
      // if no artifact_id given, create new artifacts	
      if ($aid_column == -1) {
	$ok = check_insert_artifact($row,$data,$used_fields,$parsed_labels,$predefined_values,$errors);
	$number_inserts++;
	// if artifact_id given, verify if it exists already 
	//else send error
      } else {
	$aid = $data[$aid_column];
	if ($aid != "") {
	  $ok = check_update_artifact($row,$data,$aid,$used_fields,$parsed_labels,$predefined_values,$errors);
	  $number_updates++;
	  
	} else {
	  // have to create artifact from scratch
	  $ok = check_insert_artifact($row,$data,$used_fields,$parsed_labels,$predefined_values,$errors,true);
	  $number_inserts++;
	}	  
      }
      if (!$ok) return false;
      else $artifacts_data[] = $data;
    }
    $row++;
  }
  
  fclose($csv_file);
  if ($is_tmp) {
    unlink($csv_filename);
  }
  return true;
}




function show_errors($errors) {
  echo $errors." <br>\n";
}

function mandatory_fields($ath) {
  $art_field_fact = new ArtifactFieldFactory($ath);
  $fields =  $art_field_fact->getAllUsedFields();
  while (list(,$field) = each($fields) ) {
    if ( $field->getName() != "comment_type_id" && !$field->isEmptyOk()) {
      $mand_fields[$field->getName()] = true;
    }
  } 
  return $mand_fields;
}

function getUsedFields() {
  global $ath,$art_field_fact,$Language;
  $art_field_fact = new ArtifactFieldFactory($ath);
  $fields =  $art_field_fact->getAllUsedFields();
  while (list(,$field) = each($fields) ) {
    if ( $field->getName() != "comment_type_id" ) {
      $used_fields[$field->getLabel()] = $field;
    }
  }

  // TODO: Localize this properly by adding those 4 fields to the artifact table
  // (standard fields) and the artifact field table with a special flag and make sure
  // all tracker scripts handle them properly
  // For now make a big hack!! (see import.php func=showformat)
  $submitted_field = $art_field_fact->getFieldFromName('submitted_by');
  //print_r($submitted_field);
  if (strstr($submitted_field->getLabel(),"ubmit")) {
      // Assume English
      $used_fields["Follow-up Comments"] = "";
      $used_fields["Depend on"] = "";
      $used_fields["CC List"] = "";
      $used_fields["CC Comment"] = "";
  } else {
      // Assume French
      $used_fields["Commentaires"] = "";
      $used_fields["D�pend de"] = "";
      $used_fields["Liste CC"] = "";
      $used_fields["Commentaire CC"] = "";
  }        

  $submitted_by_field = $art_field_fact->getFieldFromName('submitted_by');
  $submitted_by_label = $submitted_by_field->getLabel();
  //special cases for submitted by and submitted on that can be set
  //"unused" by the user but that will nevertheless be used by CodeX
  if (array_key_exists($submitted_by_label, $used_fields) === false)
    $used_fields[$submitted_by_label] = $submitted_by_field;
  $open_date_field = $art_field_fact->getFieldFromName("open_date");
  $open_date_label = $open_date_field->getLabel();
  if (array_key_exists($open_date_label, $used_fields) === false)
    $used_fields[$open_date_label] = $open_date_field; 

  return $used_fields;
}

function get_import_user(&$sub_user_id,&$sub_user_name) {
  global $user_id,$ath;

  $sub_user_id = $user_id;

  if (!$ath->userIsAdmin() && !$ath->userIsTech()) {
    exit_permission_denied();
  } else {
    $sub_user_name = user_getname();
  }
}

/** get already the predefined values of this field (if applicable) 
 * @param $used_fields: array containing all the fields that are used in this tracker
 * @param $parsed_labels (OUT): array of the form (column_number => field_label) containing
 *                              all the fields parsed from $data
 * @return $predefined_values: array of the form (column_number => array of field predefined values)
*/
function getPredefinedValues($used_fields,$parsed_labels) {
  global $ath;

  for ($c=0; $c < count($parsed_labels); $c++) {
    $field_label = $parsed_labels[$c];
    $curr_field = $used_fields[$field_label];
    if ($curr_field != "" && 
	($curr_field->getDisplayType() == "SB" || $curr_field->getDisplayType() == "MB")) {

      //special case for submitted by
      if ($curr_field->getName() == "submitted_by") {
	// simply put nothing in predefined values for submitted_by
	// as we accept all logged users, even None for allow-anon trackers
	
	//for all other fields not submitted by
      } else {
	
	$predef_val = $curr_field->getFieldPredefinedValues($ath->getID());
	$count = db_numrows($predef_val);
	for ($i=0;$i<$count;$i++) {
	  $values[db_result($predef_val,$i,1)] = db_result($predef_val,$i,0);
	}
	$predefined_values[$c] = $values;
      }
    }
  }
  return $predefined_values;
}



/** assume that the 
 * @param details (IN): details have the form that we get when exporting details in csv format
 *                      (see ArtifactHtml->showDetails(ascii = true))
 * @param parsed_details (OUT): an array (#detail => array2), where array2 is of the form
 *                              ("date" => date, "by" => user, "type" => comment-type, "comment" => comment-string)
 * @param for_parse_report (IN): if we parse the details to show them in the parse report then we keep the labels
 *                               for users and comment-types
 */
function parse_details($details,&$parsed_details,&$errors,$for_parse_report=false) {
  global $sys_lf, $art_field_fact, $ath, $sys_datefmt,$user_id,$Language;

  //echo "<br>\n";
  $comments = split("------------------------------------------------------------------",$details);

  $i = 0;
  while (list(,$comment) = each($comments)) {
    $i++;
    if (($i == 1) && 
	( (count($comments) > 1) || 
	  (trim($comment) == $Language->getText('tracker_import_utils','no_followups')) ) ) {
      //skip first line
      continue;
    }
    $comment = trim($comment);
    
    //skip the "Date: "
    if (strpos($comment, $Language->getText('tracker_import_utils','date').":") === false) {
      //if no date given, consider this whole string as the comment

      //try nevertheless if we can apply legacy Bug and Task export format
      if (parse_legacy_details($details,&$parsed_details,&$errors,$for_parse_report)) {
	return true;
      } else {
	if ($for_parse_report) {
	  $date= format_date($sys_datefmt,time());
	  get_import_user($sub_user_id,$sub_user_name);
	  $arr["date"] = "<I>$date</I>";
	  $arr["by"] = "<I>$sub_user_name</I>";
	  $arr["type"] = "<I>".$Language->getText('global','none')."</I>";
	} else {
	  $arr["date"] = time();
	  $arr["by"] = $user_id;
	  $arr["type"] = 100;
	}
	$arr["comment"] = $comment;
	$parsed_details[] = $arr;
	continue;
      }
    }

    // here starts reel parsing
    $comment = substr($comment, 6);
    $by_position = strpos($comment,$Language->getText('global','by').": ");
    if ($by_position === false) {
      $errors .= $Language->getText('tracker_import_utils','specify_originator',array($i-1,$comment));
      return false;
    }
    $date_str = trim(substr($comment, 0, $by_position));
    //echo "$date_str<br>";
    if ($for_parse_report) $date = $date_str;
    else list($date,$ok) = util_importdatefmt_to_unixtime($date_str);
    //echo "$date<br>";
    //skip "By: "
    $comment = substr($comment, ($by_position + 4));

    $by = strtok($comment," \n\t\r\0\x0B");
    $comment = trim(substr($comment,strlen($by)));

    if ($by == $Language->getText('global','none')) {
      $errors .= $Language->getText('tracker_import_utils','specify_valid_user',$i-1);
      return false;
    }
    if (!$for_parse_report) {
      $res = user_get_result_set_from_unix($by);
      if (db_numrows($res) > 0) {
	$by = db_result($res,0,'user_id');
      } else if (validate_email($by)) {
	//ok, $by remains what it is
      } else {
	$errors .= $Language->getText('tracker_import_utils','not_a_user',array($by,$i-1));
	return false;
      }
    }

    //see if there is comment-type or none
    $comment_type_id = false;
    $type_end_pos = strpos($comment,"]");
    if (strpos($comment,"[") == 0 &&  $type_end_pos!= false) {
      $comment_type = substr($comment, 1, ($type_end_pos-1));
      $comment = trim(substr($comment,($type_end_pos+1)));
      
      $comment_type_id = check_comment_type($comment_type);
    }

    if ($comment_type_id === false) {
      if ($for_parse_report) $comment_type_id = $Language->getText('global','none');
      else $comment_type_id = 100;
    } else if ($for_parse_report) {
      $comment_type_id = $comment_type;
    }
    
    $arr["date"] = $date;
    $arr["by"] = $by;
    $arr["type"] = $comment_type_id;
    $arr["comment"] = $comment;
    $parsed_details[] = $arr;
    unset($comment_type_id);
  }
  
  return true;
}


/** check whether this is really a valid comment_type
 * and if it is the case return its id else return false 
 */
function check_comment_type($type) {
  global $ath, $art_field_fact;

  $comment_type_id = false;

  $c_type_field = $art_field_fact->getFieldFromName('comment_type_id');
  if ($c_type_field) {
    $predef_val = $c_type_field->getFieldPredefinedValues($ath->getID());
    $count = db_numrows($predef_val);
    for ($p=0;$p<$count;$p++) {
      if ($comment_type == db_result($predef_val,$p,1)) {
	$comment_type_id = db_result($predef_val,$p,0);
	break;
      }
    }
  }
  return $comment_type_id;
}


/** assume that the details input format is
 * ==================================================
 * [Type:<type>] By:<by> On:<date>
 *
 * <comment>
 *
 * @param details (IN): see above
 * @param parsed_details (OUT): an array (#detail => array2), where array2 is of the form
 *                              ("date" => date, "by" => user, "type" => comment-type, "comment" => comment-string)
 * @param for_parse_report (IN): if we parse the details to show them in the parse report then we keep the labels
 *                               for users and comment-types
 */
function parse_legacy_details($details,&$parsed_details,&$errors,$for_parse_report=false) {
  global $sys_lf, $art_field_fact, $ath, $sys_datefmt,$user_id,$Language;

  $comments = split("==================================================",$details);

  $i = 0;
  while (list(,$comment) = each($comments)) {

    $i++;
    if ($i==1) continue;

    $comment = trim($comment);
    //skip the "Type: "
    if (strpos($comment, $Language->getText('tracker_import_utils','type').": ") === false) {
      //if no type given, consider this whole string as the comment
      if ($for_parse_report) $comment_type = $Language->getText('global','none');
      else $comment_type = 100;
    } else {
      $comment = substr($comment, 6);
      $by_position = strpos($comment,$Language->getText('global','by').": ");
      if ($by_position === false) {
	$errors .= $Language->getText('tracker_import_utils','specify_originator',array($i-1,$comment));
	return false;
      }
      $type = trim(substr($comment,0,$by_position));
      $comment_type_id = check_comment_type($type);
      if ($comment_type_id === false) {
	if ($for_parse_report) $comment_type = $Language->getText('global','none');
	else $comment_type = 100;
      } else {
	if ($for_parse_report) $comment_type = $type;
	else $comment_type = $comment_type_id;
      }
    }

    // By:
    $by_position = strpos($comment,$Language->getText('global','by').": ");
    if ($by_position === false) {
      $errors .= $Language->getText('tracker_import_utils','specify_originator',array($i-1,$comment));
      return false;
    }
    
    $comment = substr($comment, ($by_position + 4));
    $on_position = strpos($comment, $Language->getText('global','on').": ");
    $by = trim(substr($comment, 0, $on_position));


    if (!$for_parse_report) {
      $res = user_get_result_set_from_unix($by);
      if (db_numrows($res) > 0) {
	$by = db_result($res,0,'user_id');
      } else if (validate_email($by)) {
	//ok, $by remains what it is
      } else {
	$errors .= $Language->getText('tracker_import_utils','not_a_user',array($by,$i-1));
	return false;
      }
    }

    // On:
    $comment = substr($comment, ($on_position+4));
    $on = strtok($comment,"\n\t\r\0\x0B");
    $comment = trim(substr($comment,strlen($on)));
    if (!$for_parse_report) list($on,$ok) = util_importdatefmt_to_unixtime($on);
    
    
    $arr["date"] = $on;
    $arr["by"] = $by;
    $arr["type"] = $comment_type;
    $arr["comment"] = trim($comment);
    $parsed_details[] = $arr;
  }
  
  return true;
}



/**
 * prepare our $data record so that we can use standard artifact methods to create, update, ...
 * the imported artifact
 */
function prepare_vfl($data,$used_fields,$parsed_labels,$predefined_values,&$artifact_depend_id,&$add_cc,&$cc_comment,&$details) {
  global $Language,$art_field_fact;
  for ($c=0; $c < count($data); $c++) {
    $label = $parsed_labels[$c];
    $field = $used_fields[$label];
    if ($field) $field_name = $field->getName();
    $imported_value = $data[$label];

    // TODO: Localize this properly by adding those 4 fields to the artifact table
    // (standard fields) and the artifact field table with a special flag and make sure
    // all tracker scripts handle them properly
    // For now make a big hack!! (see import.php func=showformat)
    $submitted_field = $art_field_fact->getFieldFromName('submitted_by');
    if (strstr($submitted_field->getLabel(),"ubmit")) {
	// Assume English
	$lbl_follow_ups = 'Follow-up Comments';
	$lbl_s_dependent_on = 'Depend on';
	$lbl_add_cc = 'CC List';
	$lbl_cc_comment = 'CC Comment';
    } else {
	// Assume French
	$lbl_follow_ups = 'Commentaires';
	$lbl_is_dependent_on = 'D�pend de';
	$lbl_add_cc = 'Liste CC';
	$lbl_cc_comment = 'Commentaire CC';
    }

    // FOLLOW-UP COMMENTS
    if ($label == $lbl_follow_ups) {
      $field_name = "details";
      if ($data[$label] != "" && trim($data[$label]) != $Language->getText('tracker_import_utils','no_followups')) {
	$details = $data[$label];
      }
      continue;
      
    // DEPEND ON
    } else if ($label == $lbl_s_dependent_on) {
      $depends = $data[$label];
      if ($depends != $Language->getText('global','none') && $depends != "") {
	$artifact_depend_id = $depends;
      } else {
	//we have to delete artifact_depend_ids if nothing has been specified
	$artifact_depend_id = $Language->getText('global','none');
      }
      continue;
    
    // CC LIST
    } else if ($label == $lbl_add_cc) {
      if ($data[$label] != "" && $data[$label] != $Language->getText('global','none'))
      $add_cc = $data[$label];
      else $add_cc = "";
      continue;

    // CC COMMENT
    } else if ($label == $lbl_cc_comment) {
      $cc_comment = $data[$label];
      continue;

    // ORIGINAL SUBMISSION
      //special treatment for "Original Submission" alias "details"
      //in the import. The follow-up comments are also named "details"
      //and in an import (in contrast to a normal create) we can
      //have both information "original submission" AND "follow-up comments"
    } else if ($field_name == "details") {
      $vfl["original_submission"] = $data[$label];
      continue;
    
    // SUBMITTED BY
    } else if ($field_name == "submitted_by") {
      $sub_user_name = $data[$label];
      if ($sub_user_name && $sub_user_name != "") {
	$res = user_get_result_set_from_unix($sub_user_name);
	$imported_value = db_result($res,0,'user_id');
      }
      $vfl[$field_name] = $imported_value;
      continue;
    } 

  
    
    
    // transform imported_value into format that can be inserted into db
    unset($value);
    unset($predef_vals);
    $predef_vals = $predefined_values[$c];
    if ($predef_vals) {
      if ($field && $field->getDisplayType() == "MB") {
	$val_arr = explode(",",$imported_value);
	while (list(,$name) = each($val_arr)) {
	  if ($name == $Language->getText('global','none')) $value[] = 100;
	  else $value[] = $predef_vals[$name];
	}
      } else {

	if ($imported_value == $Language->getText('global','none')) $value = 100;
	else $value = $predef_vals[$imported_value];

	//special case for severity where we allow to specify
	// 1 instead of "1 - Ordinary"
	// 5 instead of "5 - Major"
	// 9 intead of "9 - Critical"
	if ($field_name == "severity" &&
	    (strcasecmp($imported_value,'1') == 0 ||
	     strcasecmp($imported_value,'5') == 0 ||
	     strcasecmp($imported_value,'9') == 0)) {
	  $value = $imported_value;
	}
      }
      $vfl[$field_name] = $value; 


    // IT COULD BE SO SIMPLE !!!
    } else {
      $vfl[$field_name] = $imported_value;
    }
  }

  return $vfl;
}



/** check if all the values correspond to predefined values of the corresponding fields */
function insert_artifact($row,$data,$used_fields,$parsed_labels,$predefined_values,&$errors) {
  global $ath,$Language;
  
  //prepare everything to be able to call the artifacts create method
  $ah=new ArtifactHtml($ath);
  if (!$ah || !is_object($ah)) {
    exit_error($Language->getText('global','error'),$Language->getText('tracker_index','not_create_art'));
  } else {
    // Check if a user can submit a new without loggin
    if ( !user_isloggedin() && !$ath->allowsAnon() ) {
      exit_not_logged_in();
      return;
    }
    
    //
    //  make sure this person has permission to add artifacts
    //
    if (!$ath->userIsAdmin()) {
      exit_permission_denied();
    }
    
    $vfl = prepare_vfl($data,$used_fields,$parsed_labels,$predefined_values,$artifact_depend_id,$add_cc,$cc_comment,$details);
   

    // Artifact creation        
    if (!$ah->create($vfl,true,$row)) {
      exit_error($Language->getText('global','error'),$ah->getErrorMessage());
    }
    //handle dependencies and such stuff ...
    if ($artifact_depend_id) {
      if (!$ah->addDependencies($artifact_depend_id,$changes,false)) {
	$errors .= $Language->getText('tracker_import_utils','problem_insert_dependent',$ah->getID())." ";
	//return false;
      }
    }
    if ($add_cc) {
      if (!$ah->addCC($add_cc,$cc_comment,$changes)) {
	$errors .= $Language->getText('tracker_import_utils','problem_add_cc',$ah->getID())." ";
      }
    }

    if ($details) {
      if (parse_details($details,$parsed_details,$errors)) {
	if (!$ah->addDetails($parsed_details)) {
	  $errors .= $Language->getText('tracker_import_utils','problem_insert_followup',$ah->getID())." ";
	  return false;
	}
      } else {
	return false;
      }
    }
  }
  return true;
}




function update_artifact($row,$data,$aid,$used_fields,$parsed_labels,$predefined_values,$errors) {
  global $ath, $feedback,$Language;

  $ah=new ArtifactHtml($ath,$aid);
  if (!$ah || !is_object($ah)) {
    exit_error($Language->getText('global','error'),$Language->getText('tracker_index','not_create_art'));
  } else if ($ah->isError()) {
    exit_error($Language->getText('global','error'),$ah->getErrorMessage());
  } else {
    
    // Check if users can update anonymously
    if ( !user_isloggedin() && !$ath->allowsAnon()  ) {
      exit_not_logged_in();
    }
    
    if ( !$ah->ArtifactType->userIsAdmin() ) {
      exit_permission_denied();
      return;
    }
    
    $vfl = prepare_vfl($data,$used_fields,$parsed_labels,$predefined_values,$artifact_depend_id,$add_cc,$cc_comment,$details);

    //data control layer
    if (!$ah->handleUpdate($artifact_depend_id,100,$changes,false,$vfl,true)) {
      exit_error($Language->getText('global','error'),$feedback);
    }
    if ($add_cc) {
      if (!$ah->updateCC($add_cc,$cc_comment)) {
	$errors .= $Language->getText('tracker_import_utils','problem_add_cc',$ah->getID())." ";
      }
    }

    if ($details) {
      if (parse_details($details,$parsed_details,$errors)) {
	if (!$ah->addDetails($parsed_details)) {
	  $errors .= $Language->getText('tracker_import_utils','problem_insert_followup',$ah->getID())." ";
	  return false;
	}
      } else {
	return false;
      }
    }
  }
  return true;
}


/**
 * Insert or update the imported artifacts into the db
 * @param parsed_labels: array of the form (column_number => field_label) containing
 *                              all the fields parsed from $data
 * @param artifacts_data: all artifacts in an array. artifacts are in the form array(field_label => value) 
 * @param $aid_column: the column in the csv file that contains the arifact id (-1 if not given)
 * @param $errors (OUT): string containing explanation what error occurred
 * @return true if parse ok, false if errors occurred
 */
function update_db($parsed_labels,$artifacts_data,$aid_column,&$errors) {
  global $ath,$art_field_fact;
  
  $used_fields = getUsedFields();
  $predefined_values = getPredefinedValues($used_fields,$parsed_labels);
  
  for ($i=0; $i < count($artifacts_data); $i++) {
    $data = $artifacts_data[$i];
    if ($aid_column == -1) {
      $ok = insert_artifact($i+2,$data,$used_fields,$parsed_labels,$predefined_values,$errors);
      
      // if artifact_id given, verify if it exists already 
      //else send error
    } else {
      $aid_field = $art_field_fact->getFieldFromName('artifact_id');
      $aid_label = $aid_field->getLabel();
      $aid = $data[$aid_label];
      if ($aid != "") {
	$ok = update_artifact($row,$data,$aid,$used_fields,$parsed_labels,$predefined_values,$errors);
	
      } else {
	// have to create artifact from scratch
	$ok = insert_artifact($i+2,$data,$used_fields,$parsed_labels,$predefined_values,$errors);
      }	  
    }
    if (!$ok) return false;
  }
  return true;
  
}



?>
