<?php
/**
 * Copyright (c) Enalean, 2012. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__).'/../../../../include/Tracker/Chart/Data/BurndownTimePeriod.class.php';

class Tracker_Chart_Data_BurndownTimePeriodTest extends TuleapTestCase {
    
    public function itComputesDateBasedOnStartDate() {
        $start_date    = mktime(0, 0, 0, 7, 4, 2012);
        $time_period   = new Tracker_Chart_Data_BurndownTimePeriod($start_date, 2);
        
        $this->assertEqual($time_period->getHumanReadableDates(), array('Jul-04', 'Jul-05', 'Jul-06'));
    }
    
    public function itProvidesAListOfTheDayOffsetsInTheTimePeriod() {
        $start_date    = mktime(0, 0, 0, 7, 4, 2012);
        $time_period   = new Tracker_Chart_Data_BurndownTimePeriod($start_date, 2);
        
        $this->assertEqual($time_period->getDayOffsets(), array(0, 1, 2));
    }
}
?>
