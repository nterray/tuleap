<?php
/**
 * Copyright (c) Enalean, 2018. All Rights Reserved.
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

namespace Tuleap\GitLFS\Admin;

use Tuleap\DB\DataAccessObject;

class AdminDao extends DataAccessObject
{
    public function getFileMaxSize()
    {
        return $this->getDB()->single('SELECT size FROM plugin_gitlfs_file_max_size');
    }

    public function updateFileMaxSize($current_max_file_size, $new_max_file_value)
    {
        return $this->getDB()->update(
            'plugin_gitlfs_file_max_size',
            ['size' => $new_max_file_value],
            ['size' => $current_max_file_size]
        );
    }
}
