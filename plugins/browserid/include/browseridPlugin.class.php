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

require_once 'common/plugin/Plugin.class.php';

/**
 * BrowserIDPlugin
 */
class BrowserIDPlugin extends Plugin {

    /**
     * Plugin constructor
     */
    function __construct($id) {
        parent::__construct($id);
        $this->setScope(self::SCOPE_PROJECT);
        $this->_addHook('javascript_file', 'jsFile', false);
    }

    /**
     * @return BrowserIDPluginInfo
     */
    function getPluginInfo() {
        if (!$this->pluginInfo) {
            include_once 'BrowserIDPluginInfo.class.php';
            $this->pluginInfo = new BrowserIDPluginInfo($this);
        }
        return $this->pluginInfo;
    }

    function jsFile($params) {
        echo '<script src="https://login.persona.org/include.js" type="text/javascript"></script>';
        echo '<script type="text/javascript" src="'.$this->getPluginPath().'/script.js"></script>'."\n";
    }

    public function process(Codendi_Request $request) {
        $assertion = $request->get('assertion');
        if ($request->isAjax() && $assertion) {
            header('Content-Type: application/json');
            $result = $this->checkAssertion($assertion);
            if ($result) {
                $matching_users = $this->getMatchingUsers($result->email);
                if ($matching_users) {
                    if (count($matching_users) > 1) {
                        $choosen_user = $request->get('choosen_user');
                        if ($choosen_user) {
                            foreach ($matching_users as $matching_user) {
                                if ($matching_user->getId() == $choosen_user) {
                                    $this->sendTheUserItsSessionHash($matching_user);
                                    return;
                                }
                            }
                        }
                        $this->letTheRequesterChooseItsUser($matching_users, $assertion);
                    } else {
                        $this->sendTheUserItsSessionHash($matching_users[0]);
                    }
                } else {
                    $this->userDoesNotExist();
                }
            } else {
                echo json_encode(array("error" => $this->getError()));
            }
        }
    }

    private function letTheRequesterChooseItsUser(array $users, $assertion) {
        $users_data = array();
        foreach ($users as $user) {
            $users_data[] = array(
                                  'id'       => $user->getId(),
                                  'name'     => $user->getUserName(),
                                  'realname' => $user->getRealName(),
                                 );
        }
        echo json_encode(array('choose_user' => $users_data, 'assertion' => $assertion));
    }

    private function sendTheUserItsSessionHash(User $user) {
        $GLOBALS['Response']->addFeedback('info', 'Welcome back '. $user->getRealName());
        $dao          = new UserDao();
        $session_hash = $dao->createSession($user->getId(), $_SERVER['REQUEST_TIME']);
        $expire       = 0;

        $cm = new CookieManager();
        $cm->setCookie('session_hash', $session_hash, $expire);
        echo json_encode(array('realname' => $user->getRealName()));
    }

    private function userDoesNotExist() {
        //only if user registration is allowed
        echo json_encode(array('redirect' => '/account/register.php'));
    }

    private function getMatchingUsers($email) {
        $um = UserManager::instance();
        return $um->getAllUsersByEmail($email);
    }

    private function checkAssertion($assertion) {
        $url = 'https://verifier.login.persona.org/verify';
        $fields = array(
            'assertion' => $assertion,
            'audience'  => Config::get('sys_default_domain'),
        );
        $fields_string = http_build_query($fields);

        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, count($fields));
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($this->ch);
        //curl_close($ch);

        $json_result = json_decode($result);
        if ($json_result && $json_result->status === 'okay') {
            return $json_result;
        }
    }
    
    private function getError() {
        return curl_error($this->ch);
    }
}

?>
