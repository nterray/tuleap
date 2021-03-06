/*
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

import { mockFetchError } from "tlp-mocks";
import { loadFolderContent, loadAscendantHierarchy, loadRootDocumentId } from "./actions.js";
import {
    restore as restoreRestQuerier,
    rewire$getFolderContent,
    rewire$getItem,
    rewire$getParents,
    rewire$getProject
} from "../api/rest-querier.js";

describe("Store actions", () => {
    afterEach(() => {
        restoreRestQuerier();
    });

    let context, getFolderContent, getProject, getItem, getParents;

    beforeEach(() => {
        const project_id = 101;
        context = {
            commit: jasmine.createSpy("commit"),
            state: {
                project_id,
                current_folder_ascendant_hierarchy: []
            }
        };

        getFolderContent = jasmine.createSpy("getFolderContent");
        rewire$getFolderContent(getFolderContent);

        getParents = jasmine.createSpy("getParents");
        rewire$getParents(getParents);

        getProject = jasmine.createSpy("getProject");
        rewire$getProject(getProject);

        getItem = jasmine.createSpy("getItem");
        rewire$getItem(getItem);
    });

    describe("loadRootDocumentId()", () => {
        it("load document root and then load its own content", async () => {
            const project = {
                additional_informations: {
                    docman: {
                        root_item: {
                            id: 3,
                            title: "Project Documentation",
                            owner: {
                                id: 101,
                                display_name: "user (login)"
                            },
                            last_update_date: "2018-08-21T17:01:49+02:00"
                        }
                    }
                }
            };

            getProject.and.returnValue(project);

            const folder_content = [
                {
                    id: 1,
                    title: "folder",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-10-03T11:16:11+02:00"
                },
                {
                    id: 2,
                    title: "item",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-08-07T16:42:49+02:00"
                }
            ];

            getFolderContent.and.returnValue(folder_content);

            await loadRootDocumentId(context);

            expect(context.commit).toHaveBeenCalledWith("beginLoading");
            expect(context.commit).toHaveBeenCalledWith("saveFolderContent", folder_content);
            expect(context.commit).toHaveBeenCalledWith("stopLoading");
        });

        it("When the user does not have access to the project, an error will be raised", async () => {
            mockFetchError(getProject, {
                status: 403,
                error_json: {
                    error: {
                        message: "User can't access project"
                    }
                }
            });

            await loadRootDocumentId(context);

            expect(context.commit).toHaveBeenCalledWith("switchFolderPermissionError");
            expect(context.commit).toHaveBeenCalledWith("stopLoading");
        });

        it("When the project can't be found, an error will be raised", async () => {
            const error_message = "Project does not exist.";
            mockFetchError(getProject, {
                status: 404,
                error_json: {
                    error: {
                        message: error_message
                    }
                }
            });

            await loadRootDocumentId(context);

            expect(context.commit).toHaveBeenCalledWith("setFolderLoadingError", error_message);
            expect(context.commit).toHaveBeenCalledWith("stopLoading");
        });
    });

    describe("loadFolderContent", () => {
        it("loads the folder content and sets loading flag", async () => {
            const folder_content = [
                {
                    id: 1,
                    title: "folder",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-10-03T11:16:11+02:00"
                },
                {
                    id: 2,
                    title: "item",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-08-07T16:42:49+02:00"
                }
            ];

            getFolderContent.and.returnValue(folder_content);

            await loadFolderContent(context);

            expect(context.commit).toHaveBeenCalledWith("beginLoading");
            expect(context.commit).toHaveBeenCalledWith("saveFolderContent", folder_content);
            expect(context.commit).toHaveBeenCalledWith("stopLoading");
        });

        it("When the folder can't be found, another error screen will be shown", async () => {
            const error_message = "The folder does not exist.";
            mockFetchError(getFolderContent, {
                status: 404,
                error_json: {
                    error: {
                        i18n_error_message: error_message
                    }
                }
            });

            await loadFolderContent(context);

            expect(context.commit).not.toHaveBeenCalledWith("saveFolderContent");
            expect(context.commit).toHaveBeenCalledWith("setFolderLoadingError", error_message);
            expect(context.commit).toHaveBeenCalledWith("stopLoading");
        });

        it("When the user does not have access to the folder, an error will be raised", async () => {
            mockFetchError(getFolderContent, {
                status: 403,
                error_json: {
                    error: {
                        i18n_error_message: "No you cannot"
                    }
                }
            });

            await loadFolderContent(context);

            expect(context.commit).not.toHaveBeenCalledWith("saveFolderContent");
            expect(context.commit).toHaveBeenCalledWith("switchFolderPermissionError");
            expect(context.commit).toHaveBeenCalledWith("stopLoading");
        });
    });

    describe("loadAscendantHierarchy", () => {
        it("loads the folder parents and sets loading flag", async () => {
            const parents = [
                {
                    id: 1,
                    title: "Project documentation",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-10-03T11:16:11+02:00"
                },
                {
                    id: 2,
                    title: "folder A",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-08-07T16:42:49+02:00"
                }
            ];

            const item = {
                id: 3,
                title: "Current folder",
                owner: {
                    id: 101,
                    display_name: "user (login)"
                },
                last_update_date: "2018-08-21T17:01:49+02:00"
            };

            const expected_parents = [
                {
                    id: 2,
                    title: "folder A",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-08-07T16:42:49+02:00"
                },
                {
                    id: 3,
                    title: "Current folder",
                    owner: {
                        id: 101,
                        display_name: "user (login)"
                    },
                    last_update_date: "2018-08-21T17:01:49+02:00"
                }
            ];

            getItem.and.returnValue(item);

            getParents.and.returnValue(parents);

            await loadAscendantHierarchy(context);

            expect(context.commit).toHaveBeenCalledWith("beginLoadingAscendantHierarchy");
            expect(context.commit).toHaveBeenCalledWith("saveAscendantHierarchy", expected_parents);
            expect(context.commit).toHaveBeenCalledWith("stopLoadingAscendantHierarchy");
        });

        it("When target folder is in the list of parents, then it does not fetch parents", async () => {
            context.state.current_folder_ascendant_hierarchy = [
                {
                    id: 2,
                    title: "folder A",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-08-07T16:42:49+02:00"
                },
                {
                    id: 3,
                    title: "folder B",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-08-07T16:42:49+02:00"
                }
            ];

            const expected_parents = [
                {
                    id: 2,
                    title: "folder A",
                    owner: {
                        id: 101
                    },
                    last_update_date: "2018-08-07T16:42:49+02:00"
                }
            ];

            await loadAscendantHierarchy(context, 2);

            expect(getParents).not.toHaveBeenCalled();
            expect(getItem).not.toHaveBeenCalled();
            expect(context.commit).not.toHaveBeenCalledWith("beginLoadingAscendantHierarchy");
            expect(context.commit).toHaveBeenCalledWith("saveAscendantHierarchy", expected_parents);
            expect(context.commit).not.toHaveBeenCalledWith("stopLoadingAscendantHierarchy");
        });

        it("When the parents can't be found, another error screen will be shown", async () => {
            const error_message = "The folder does not exist.";
            mockFetchError(getParents, {
                status: 404,
                error_json: {
                    error: {
                        i18n_error_message: error_message
                    }
                }
            });

            await loadAscendantHierarchy(context);

            expect(context.commit).not.toHaveBeenCalledWith("saveAscendantHierarchy");
            expect(context.commit).toHaveBeenCalledWith("setFolderLoadingError", error_message);
            expect(context.commit).toHaveBeenCalledWith("stopLoadingAscendantHierarchy");
        });

        it("When the item can't be found, another error screen will be shown", async () => {
            const error_message = "The folder does not exist.";
            mockFetchError(getItem, {
                status: 404,
                error_json: {
                    error: {
                        i18n_error_message: error_message
                    }
                }
            });

            await loadAscendantHierarchy(context);

            expect(context.commit).not.toHaveBeenCalledWith("saveAscendantHierarchy");
            expect(context.commit).toHaveBeenCalledWith("setFolderLoadingError", error_message);
            expect(context.commit).toHaveBeenCalledWith("stopLoadingAscendantHierarchy");
        });

        it("When the user does not have access to the folder, an error will be raised", async () => {
            mockFetchError(getParents, {
                status: 403,
                error_json: {
                    error: {
                        i18n_error_message: "No you cannot"
                    }
                }
            });

            await loadAscendantHierarchy(context);

            expect(context.commit).not.toHaveBeenCalledWith("saveAscendantHierarchy");
            expect(context.commit).toHaveBeenCalledWith("switchFolderPermissionError");
            expect(context.commit).toHaveBeenCalledWith("stopLoadingAscendantHierarchy");
        });
    });
});
