<?php
/**
 * Copyright (c) Enalean, 2013 - 2018. All Rights Reserved.
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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * Returns all tasks id in a Release
 *
 * It leverage on ArtifactLink information and will recrusively inspect the
 * milestone links (from top to bottom) and keep all artifacts that belongs to
 * the backlog tracker.
 *
 * This is the same type of algorithm than used in AgileDashboard_Milestone_Backlog_ArtifactsFinder
 */
class AgileDashboard_BacklogItem_SubBacklogItemProvider {

    /** @var Tracker_ArtifactDao */
    private $dao;

    /** @var Integer[] */
    private $backlog_ids = array();

    /** @var Integer[] */
    private $inspected_ids = array();

    /** @var AgileDashboard_Milestone_Backlog_BacklogItemCollectionFactory */
    private $backlog_item_collection_factory;

    /** @var AgileDashboard_Milestone_Backlog_BacklogFactory */
    private $backlog_factory;

    public function __construct(Tracker_ArtifactDao $dao,
        AgileDashboard_Milestone_Backlog_BacklogFactory $backlog_factory,
        AgileDashboard_Milestone_Backlog_BacklogItemCollectionFactory $backlog_item_collection_factory
    ) {
        $this->backlog_item_collection_factory = $backlog_item_collection_factory;
        $this->backlog_factory                 = $backlog_factory;
        $this->dao                             = $dao;
    }

    /**
     * Return all indexed ids of artifacts linked on milestone that belong to backlog tracker
     *
     * @param Planning_Milestone $milestone
     * @param Tracker $backlog_tracker
     * @param PFUser $user
     * @return array
     */
    public function getMatchingIds(Planning_Milestone $milestone, Tracker $backlog_tracker, PFUser $user) {
        if (! $milestone->getArtifactId()) {
            return $this->getMatchingIdsForTopBacklog($milestone, $backlog_tracker, $user);
        }

        return $this->getMatchingIdsForMilestone($milestone, $backlog_tracker);
    }

    private function getMatchingIdsForMilestone(Planning_Milestone $milestone, Tracker $backlog_tracker) {
        $milestone_id_seed = array($milestone->getArtifactId());

        $this->inspected_ids = $milestone_id_seed;
        $this->filterBacklogIds($backlog_tracker->getId(), $milestone_id_seed);

        return $this->backlog_ids;
    }

    private function getMatchingIdsForTopBacklog(Planning_VirtualTopMilestone $milestone, Tracker $backlog_tracker, PFUser $user) {
        $backlog_unassigned = $this->backlog_factory->getSelfBacklog($milestone);
        $backlog_items      = $this->backlog_item_collection_factory->getUnassignedOpenCollection($user, $milestone, $backlog_unassigned, false);

        foreach ($backlog_items as $backlog_item) {
            if ($backlog_item->getArtifact()->getTrackerId() == $backlog_tracker->getId()) {
                $this->backlog_ids[$backlog_item->getArtifact()->getId()] = true;
            }
        }

        return $this->backlog_ids;
    }

    /**
     * Retrieve all linked artifacts and keep only those that belong to backlog tracker
     *
     * We need to keep list of ids we already looked at so we avoid cycles.
     *
     * @param int $backlog_tracker_id
     * @param array $artifacts
     */
    private function filterBacklogIds($backlog_tracker_id, array $artifacts) {
        $artifacts_to_inspect = array();
        foreach ($this->dao->getLinkedArtifactsByIds($artifacts, $this->inspected_ids) as $artifact_row) {
            $artifacts_to_inspect[] = $artifact_row['id'];
            if ($artifact_row['tracker_id'] == $backlog_tracker_id) {
                $this->backlog_ids[$artifact_row['id']] = true;
            }
            $this->inspected_ids[] = $artifact_row['id'];
        }
        if (count($artifacts_to_inspect) > 0) {
            $this->filterBacklogIds($backlog_tracker_id, $artifacts_to_inspect);
        }
    }
}
