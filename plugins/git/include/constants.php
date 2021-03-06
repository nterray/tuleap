<?php
/**
 * Copyright (c) Enalean, 2012-2018. All Rights Reserved.
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

define('GIT_BASE_URL', '/plugins/git');
define('GIT_SITE_ADMIN_BASE_URL', '/admin/git/');
define('GIT_BASE_DIR', dirname(__FILE__));
define('GIT_TEMPLATE_DIR', GIT_BASE_DIR . '/../templates');
define('GITOLITE3_LOGS_PATH', '/var/lib/gitolite/.gitolite/logs/');

/**
 * Check if platform can use gerrit
 *
 * Parameters:
 *     'platform_can_use_gerrit' => boolean
 */
define('GIT_EVENT_PLATFORM_CAN_USE_GERRIT', 'git_event_platform_can_use_gerrit');
define('REST_GIT_PULL_REQUEST_ENDPOINTS', 'rest_git_pull_request_endpoints');
define('REST_GIT_PULL_REQUEST_GET_FOR_REPOSITORY', 'rest_git_pull_request_get_for_repository');

/**
 * Allow a plugin to append his own classes to the body DOM element in git views
 *
 * Parameters:
 *   'request' => (Input)  Codendi_Request Request
 *   'classes' => (Output) String[]        Additional classnames
 */
define('GIT_ADDITIONAL_BODY_CLASSES', 'git_additional_body_classes');

/**
 * Allow a plugin to add permitted git actions
 *
 * Parameters:
 *   'repository'        => (Input)  GitRepository Git repository
 *   'user'              => (Input)  PFUser        Current user
 *   'permitted_actions' => (Output) String[]      Permitted actions
 */
define('GIT_ADDITIONAL_PERMITTED_ACTIONS', 'git_additional_permitted_actions');

/**
 * Allow plugins to add additional notifications setup for git
 *
 * Parameters:
 *   'repository' => (Input) GitRepository Git repository currently modified
 *   'request'    => (Input) HTTPRequest   Current request
 *   'output'     => (Output) String       The HTML to present
 */
define('GIT_ADDITIONAL_NOTIFICATIONS', 'git_additional_notifications');

/**
 * Allow plugins to do something when Tuleap receive a git push
 *
 * Parameters:
 *   'repository' => (Input) GitRepository Git repository currently modified
 */
define('GIT_HOOK_POSTRECEIVE', 'git_hook_post_receive');

/**
 * Allow plugins to do something when Tuleap receive a git push with a reference
 * update
 *
 * Parameters:
 *   'repository' => (Input) GitRepository Git repository currently modified
 *   'oldrev'     => (Input) The old revision of the currently updated reference
 *   'newrev'     => (Input) The new revision of the currently updated reference
 *   'refname'    => (Input) The name of the reference being updated
 *   'user'       => (Input) The user performing the action
 */
define('GIT_HOOK_POSTRECEIVE_REF_UPDATE', 'git_hook_post_receive_ref_update');

/**
 * Allow plugins to do something when Tuleap is notified
 * that a build has been triggered or finished.
 * Parameters:
 *     'repository'       => (Input) GitRepository Git repository currently modified
 *     'branch'           => (Input) The branch being built
 *     'commit_reference' => (Input) The sha1 of the commit being built
 *     'status'           => (Input) The status of the build
 *
 * @deprecated
 */
define('REST_GIT_BUILD_STATUS', 'rest_git_build_status');
