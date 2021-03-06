/**
 * Copyright Enalean (c) 2018. All rights reserved.
 *
 * Tuleap and Enalean names and logos are registrated trademarks owned by
 * Enalean SAS. All other trademarks or names are properties of their respective
 * owners.
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

import { get, patch } from "tlp-fetch";

export {
    getTracker,
    createWorkflowTransitions,
    updateTransitionRulesEnforcement,
    resetWorkflowTransitions
};

async function getTracker(tracker_id) {
    const response = await get(`/api/trackers/${tracker_id}`);
    return response.json();
}

async function createWorkflowTransitions(tracker_id, field_id) {
    const query = JSON.stringify({
        workflow: {
            set_transitions_rules: {
                field_id
            }
        }
    });
    await patch(`/api/trackers/${tracker_id}?query=${encodeURIComponent(query)}`);
}

async function resetWorkflowTransitions(tracker_id) {
    const query = JSON.stringify({
        workflow: {
            delete_transitions_rules: true
        }
    });

    const response = await patch(`/api/trackers/${tracker_id}?query=${encodeURIComponent(query)}`);
    return response.json();
}

async function updateTransitionRulesEnforcement(tracker_id, are_transition_rules_enforced) {
    const query = JSON.stringify({
        workflow: {
            set_transitions_rules: {
                is_used: are_transition_rules_enforced
            }
        }
    });
    const response = await patch(`/api/trackers/${tracker_id}?query=${encodeURIComponent(query)}`);
    return response.json();
}
