<?php
/**
 * Copyright (c) Enalean, 2011 - 2018. All Rights Reserved.
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
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

namespace Tuleap\Project\Admin\ProjectUGroup;

use Codendi_HTMLPurifier;
use CSRFSynchronizerToken;
use EventManager;
use FRSReleaseFactory;
use PermissionsManager;
use PFUser;
use ProjectUGroup;

class ProjectUGroupPresenterBuilder
{
    /**
     * @var EventManager
     */
    private $event_manager;
    /**
     * @var PermissionsManager
     */
    private $permissions_manager;
    /**
     * @var FRSReleaseFactory
     */
    private $release_factory;
    /**
     * @var Codendi_HTMLPurifier
     */
    private $html_purifier;
    /**
     * @var MembersPresenterBuilder
     */
    private $members_builder;
    /**
     * @var BindingPresenterBuilder
     */
    private $binding_builder;
    /**
     * @var PermissionsDelegationPresenterBuilder
     */
    private $permissions_delegation_builder;

    public function __construct(
        PermissionsManager $permissions_manager,
        EventManager $event_manager,
        FRSReleaseFactory $release_factory,
        BindingPresenterBuilder $binding_builder,
        MembersPresenterBuilder $members_builder,
        PermissionsDelegationPresenterBuilder $permissions_delegation_builder
    ) {
        $this->html_purifier = Codendi_HTMLPurifier::instance();

        $this->permissions_manager  = $permissions_manager;
        $this->event_manager        = $event_manager;
        $this->release_factory      = $release_factory;
        $this->binding_builder      = $binding_builder;
        $this->members_builder      = $members_builder;

        $this->permissions_delegation_builder = $permissions_delegation_builder;
    }

    public function build(ProjectUGroup $ugroup, CSRFSynchronizerToken $csrf, PFUser $user)
    {
        $binding     = $this->binding_builder->build($ugroup, $csrf);
        $members     = $this->members_builder->build($ugroup);
        $delegation  = $this->permissions_delegation_builder->build($ugroup);

        return new ProjectUGroupPresenter($ugroup, $delegation, $binding, $members, $csrf, $user);
    }
}
