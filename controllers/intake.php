<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Intake extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        // initially no rights for any study
        $this->permissions = array(
            $this->config->item('role:contributor') => FALSE,
            $this->config->item('role:manager') => FALSE,
            $this->config->item('role:reader') => FALSE
        );

        $this->data['userIsAllowed'] = TRUE;

        // TODO: Auto load doesn't work in module?
        $this->load->model('dataset');
        $this->load->model('debug');
        $this->load->model('filesystem');
        $this->load->model('study');
        $this->load->model('rodsuser');
        $this->load->model('metadatamodel');
        $this->load->helper('date');
        $this->load->helper('language');
        $this->load->helper('intake');
        $this->load->helper('form');
        $this->load->language('intake');
        $this->load->language('errors');
        $this->load->language('form_errors');
        $this->load->library('module', array(__DIR__));
        $this->load->library('metadatafields');
        $this->load->library('pathlibrary');
        $this->load->library('SSP');
        $this->studies = $this->dataset->getStudies($this->rodsuser->getRodsAccount());
        sort($this->studies);
    }

    public function index(){
        $this->loadDirectory(true);

        $this->load->view('common-start', array(
            'styleIncludes' => array(
                'css/intake.css', 
                'lib/datatables/datatables.css', 
                'lib/chosen-select/chosen.min.css'
            ),
            'scriptIncludes' => array(
                'js/intake.js', 
                'lib/datatables/datatables.js', 
                'lib/chosen-select/chosen.jquery.min.js'
            ),
            'activeModule'   => $this->module->name(),
            'user' => array(
                'username' => $this->rodsuser->getUsername(),
            ),
        ));
        $this->load->view('intake', $this->data);
        $this->load->view('common-end');
    }

    public function metadata() {
        $this->loadDirectory();

        $this->load->view('common-start', array(
            'styleIncludes' => array(
                'css/intake.css', 
                'lib/datatables/datatables.css', 
                'lib/chosen-select/chosen.min.css',
                'lib/datetimepicker/bootstrap-datetimepicker.css'
            ),
            'scriptIncludes' => array(
                'js/intake.js', 
                'lib/datatables/datatables.js', 
                'lib/chosen-select/chosen.jquery.min.js',
                'lib/datetimepicker/moments.min.js',
                'lib/datetimepicker/bootstrap-datetimepicker.js'
            ),
            'activeModule'   => $this->module->name(),
            'user' => array(
                'username' => $this->rodsuser->getUsername(),
            ),
        ));

        $this->load->view('edit_meta', $this->data);
        $this->load->view('common-end');
    }

    
    private function loadDirectory($redirectIfInvalid = false) {
        $this->current_path = rtrim($this->input->get('dir'), "/");
        if(!$this->current_path){
            if($tempDir = $this->session->userdata('tempDir') AND $tempDir){
                $this->current_path = $tempDir;
            } else if($this->studies[0]){
                $this->current_path = sprintf(
                    "/%s/home/%s%s",
                    $this->config->item('rodsServerZone'), 
                    $this->config->item('intake-prefix'), 
                    $this->studies[0]
                );
            }
        }

        $this->data["folderValid"] = true;

        $this->urls = (object) array(
            "site" => site_url(), 
            "module" => $this->module->getModuleBase()
        );

        $rodsaccount = $this->rodsuser->getRodsAccount();
        $pathStart = $this->pathlibrary->getPathStart($this->config);

        if($this->current_path === "") {
            $this->current_path = sprintf("/%s/home", $this->config->item('rodsServerZone'));
        }
        
        if($this->current_path === sprintf("/%s/home", $this->config->item('rodsServerZone'))) {
            $this->prepareHomeLevel($rodsaccount, $pathStart, $dirs, $dataArr);
        } else {
            $segments = $this->pathlibrary->getPathSegments($rodsaccount, $pathStart, $this->current_path, $this->dir);
            if(!is_array($segments)) {
                if($redirectIfInvalid) {
                    if(sizeof($this->studies) > 0) {
                        $redirectTo = $this->getRedirect();
                        try {
                            new ProdsDir($rodsaccount, $redirectTo, true);
                            $referUrl = site_url($this->module->name(), "intake", "intake", "index") . "?dir=" . $redirectTo;
                            $message = sprintf("ntl: %s is not a valid directory", $this->current_path);
                            if($this->current_path)
                            displayMessage($this, $message, true);
                            redirect($referUrl, 'refresh');
                        } catch(RODSException $e) {
                            // Do not redirect to an invalid folder
                        }
                    }
                }
                $this->data["folderValid"] = false;
                $this->data["errorMessage"] = sprintf(lang('intake_error_project_no_exist'), $this->current_path);
                $this->prepareHomeLevel($rodsaccount, $pathStart, $dirs, $dataArr);
            } else {
                $this->pathlibrary->getCurrentLevelAndDepth($this->config, $segments, $this->head, $this->level_depth);

                $studyID = $this->validateStudyPermission($segments[0]);

                $this->getLockedStatus();
                $dirs = $this->dir->getChildDirs();
                // $files = $this->dir->getChildFiles();

                $this->session->set_userdata('tempDir', $this->current_path);
                $this->breadcrumbs = $this->getBreadcrumbLinks($pathStart, $segments);

                $snapHistory = $this->dataset->getSnapshotHistory($rodsaccount, $this->current_path);

                $dataArr = array(
                    "studyID" => $studyID,
                    "currentViewLocked" => $this->currentViewLocked,
                    "currentViewFrozen" => $this->currentViewFrozen,
                    // "files" => $files,
                    "files" => array(),
                    "level_depth" => $this->level_depth,
                    "level_depth_start" => $this->level_depth_start,
                    "permissions" => $this->permissions,
                    "snapshotHistory" => $snapHistory,
                    "previousLevelsMeta" => $this->getPreviouslevelsMeta($pathStart, $segments),
                    "previousLevelLink" => $this->getPrevLevelLink($pathStart, $segments)
                );
                

            }
        }

        $dataArr = array_merge($dataArr, array(
            "content" => "file_overview",
            "folderValid" => true,
            "url" => $this->urls,
            "head" => $this->head,
            "studies" => $this->studies,
            "breadcrumbs" => $this->breadcrumbs,
            "current_dir" => $this->current_path,
            "levelPermissions" => $this->levelPermissions,
            "nextLevelPermissions" => $this->nextLevelPermissions,
            "directories" => $dirs,
            "intake_prefix" => $this->config->item('intake-prefix'),
            "levelSize" => sizeof($this->config->item('level-hierarchy'))
        ));

        $this->data = array_merge($this->data, $dataArr);

    }

    private function prepareHomeLevel($rodsaccount, $pathStart, &$dirs, &$dataArr) {
        $this->session->set_userdata('tempDir', $this->current_path);
        $this->breadcrumbs = $this->getBreadcrumbLinks($pathStart, array());
        $this->head = $this->config->item('base-level');
        $this->levelPermissions = $this->study->getPermissionsForLevel(-1, "");
        $this->nextLevelPermissions = $this->study->getPermissionsForLevel(0, "");

        $dirs = array();
        foreach($this->studies as $key => $study) {
            try {
                $d =  new ProdsDir($rodsaccount, sprintf("%s%s", $pathStart, $study), true);
                $d->getStats();
                $dirs[] = $d;
            } catch(RODSException $e) {
                unset($this->studies[$key]);
                // Studie was loaded but doesn't exist. This should not happen, but might,
                // if intake prefix defined in the ruleset differs from the intake prefix
                // defined in the config
            }
        }

        $dataArr = array(
            "currentViewLocked" => false,
            "currentViewFrozen" => false,
            "level_depth" => -1,
            "permissions" => array($this->config->item('role:contributor') => true), // files can be viewed with at least contributor permission
            "files" => array(),
            "previousLevelLink" => $this->getPrevLevelLink($pathStart, array())

        );
    }

    private function getLockedStatus() {
        $rodsaccount = $this->rodsuser->getRodsAccount();
        $currentViewLocked = $this->dataset->getLockedStatus($rodsaccount, $this->current_path);
        $this->currentViewLocked = $currentViewLocked['locked'];
        $this->currentViewFrozen = $currentViewLocked['frozen'];
    }

    private function getPrevLevelLink($pathstart, $segments) {
        $link = sprintf("%s/intake?dir=/%s/home", $this->urls->module, $this->config->item('rodsServerZone'));
        if(sizeof($segments) === 0) {
            $link = false;
        } else if(sizeof($segments) > 1) {
            $link .= sprintf(
                "/%s%s", 
                $this->config->item('intake-prefix'), 
                implode("/", array_slice($segments, 0, sizeof($segments) - 1))
            );
        }

        return $link;
    }

    private function getBreadcrumbLinks($pathStart, $segments) {
        $breadCrumbs = array();
        $i = 0;
        $link = site_url(array($this->module->name(), "intake", "index")) . "?dir=";
        foreach(explode("/", $pathStart) as $seg) {
            if($seg === "" || $seg == $this->config->item('intake-prefix')) continue;
            else if($seg === "home") {
                $homePath = sprintf("/%s/home", $this->config->item('rodsServerZone'));
                $breadCrumbs[] = (object) array(
                    "segment" => $seg,
                    "link" => $link . $homePath,
                    "prefix" => false,
                    "postfix" => false,
                    "is_current" => $this->current_path === $homePath
                );
            } else {
               $breadCrumbs[] = (object)array(
                    "segment" => $seg, 
                    "link" => false, 
                    "prefix" => false, 
                    "postfix" => false, 
                    "is_current" => false
                );
            }
            $i++;
            
        }
        $this->level_depth_start = $i;


        $segmentBuilder = array();

        $i = 0;
        foreach($segments as $seg) {
            $segmentBuilder[] = $seg;
            $levelLink = $link . $pathStart . (implode("/", $segmentBuilder));
            $breadCrumbs[] = (object)array(
                "segment" => $seg, 
                "link" => $levelLink,
                "prefix" => ($i == 0) ? $this->config->item('intake-prefix') : false,
                "postfix" => false,
                "is_current" => (bool)($i === sizeof($segments) - 1)
            );
            $i++;
        }

        return $breadCrumbs;
    }

    private function getPreviouslevelsMeta($pathStart, $segments) {
        $levels = array();

        // Check all levels up to but not including the current one
        for($i = 0; $i < sizeof($segments) - 1; $i++) {
            $perm = $this->study->getPermissionsForLevel($i, $segments[0]);
            if($perm->canEditMeta || $perm->canViewMeta) {
                $meta = array(
                    "name" => $segments[$i],
                    "level" => ($i < sizeof($this->config->item('level-hierarchy'))) ? 
                        $this->config->item('level-hierarchy')[$i] : $this->config->item('default-level'),
                    "meta" => $this->metadatafields->getFields(
                        sprintf("%s%s", $pathStart, implode("/", array_slice($segments, 0, $i+1))),
                        true
                    )
                );
                $levels[] = (object) $meta;
            }
        }

        return $levels;
    }

    
    /**
     * Private method that validates the study permissions for
     * the current user and prepares data in the study
     * @param $studyID      The identifying name of the study
     * @return              false (bool) if the study is not valid,
     *                      or the user doesn't have permission,
     *                      the study ID otherwise
     */
    private function validateStudyPermission($studyID) {
        // get study dependant rights for current user.
        $this->permissions = $this->study->getIntakeStudyPermissions($studyID);
        $this->levelPermissions = $this->study->getPermissionsForLevel($this->level_depth, $studyID);
        $this->nextLevelPermissions = $this->study->getPermissionsForLevel($this->level_depth + 1, $studyID);
        $this->previousLevels = array();
        for($l = 0; $l < $this->level_depth && $l < sizeof($this->config->item('level-hierarchy')); $l++) {
            $this->previousLevels[] = $this->config->item('level-hierarchy')[$l];
            $this->previousLevels[$l]["permissions"] = $this->study->getPermissionsForLevel($l, $studyID);
        }

        if(sizeof($this->studies) === 0 || $this->studies[0] === false) {
            displayMessage($this, lang('intake_error_no_projects'), true);
            return false;
        }
        else if(!$this->study->validateStudy($this->studies, $studyID)){
            $message = sprintf(lang('intake_error_project_no_exist'), $studyID, $this->getRedirect(), $this->studies[0]);
            displayMessage($this, $message, true);
            return false;
        } else if(
            !($this->permissions[$this->config->item("role:contributor")] OR 
                $this->permissions[$this->config->item('role:administrator')])
        ){
            // If the user doesn't have acces, this study doesn't appear in $this->studies,
            // so the user won't get through the previous test, right?
            $message = sprintf(lang('intake_error_no_access'), $studyID, $this->getRedirect(), $this->studies[0]);
            displayMessage($this, $message, true);
            return false;
        }

        return $studyID;
    }

    /**
     * Method that generates a redirect URL if the user has permissions
     * to view other studies
     * @param $studyID (optional)       The identifying name of the study 
     *                                  that should be redirected to.
     *                                  The first of the valid studies
     *                                  is used if not provided
     * @return  A relative URL that points back to the index of this
     *          module and to a valid study, if one is available
     */
    private function getRedirect($studyID = '') {
        $segments = array($this->module->name(), "intake", "index");
        // if(!empty($this->studies)) {
        //     array_push($segments, $studyID ? $studyID : $this->studies[0]);
        // }
        return site_url($segments);
    }

    public function getGroupUsers($study) {
        $query = $this->input->get('query');
        $showAdmin = $this->input->get("showAdmins");
        $showUsers = $this->input->get("showUsers");
        $showReadonly = $this->input->get("showReadonly");

        $showAdmin = (is_null($showAdmin) || $showAdmin == "0") ? false : true;
        $showUsers = (is_null($showUsers) || $showUsers == "0") ? false : true;
        $showReadonly = (is_null($showReadonly) || $showReadonly == "0") ? false : true;


        $group = sprintf(
                "%s%s", 
                $this->config->item('intake-prefix'),
                $study
            );

        $rodsaccount = $this->rodsuser->getRodsAccount();

        $results = 
            array_values(array_filter(
                $this->study->getGroupMembers($rodsaccount, $group, $showAdmin, $showUsers, $showReadonly),
                function($val) use($query) {
                    return !(!empty($query) && strstr($val, $query) === FALSE);
                }
            ));

        $this->output
            ->set_content_type('application/json')
            ->set_output(
                json_encode(
                    $results
                )
            );
    }

    public function getDirectories() {
        $query = $this->input->get('query');
        $showProjects = $this->input->get('showProjects');
        $showStudies = $this->input->get('showStudies');
        $showDatasets = $this->input->get('showDatasets');
        $requireContribute = $this->input->get('requireContribute');
        $requireManager = $this->input->get('requireManager');

        $showProjects = (is_null($showProjects) || $showProjects == "0" || strtolower($showProjects) !== "true") ? false : true;
        $showStudies = (is_null($showStudies) || $showStudies == "0" || strtolower($showStudies) !== "true") ? false : true;
        $showDatasets = (is_null($showDatasets) || $showDatasets == "0" || strtolower($showDatasets) !== "true") ? false : true;
        $requireContribute = (is_null($requireContribute) || $requireContribute == "0" || strtolower($requireContribute) !== "true") ? false : true;
        $requireManager = (is_null($requireManager) || $requireManager == "0" || strtolower($requireManager) !== "true") ? false : true;

        $rodsaccount = $this->rodsuser->getRodsAccount();

        $results = array_values(array_filter(
            $this->study->getDirectories($rodsaccount, $showProjects, $showStudies, $showDatasets, $requireContribute, $requireManager),
            function($val) use ($query) {
                $dirArr = explode("/", $val);
                $dirName = $dirArr[sizeof($dirArr) - 1];

                return !(!empty($query) && strstr($dirName, $query) === FALSE);
            }
        ));

        $this->output
            ->set_content_type('application/json')
            ->set_output(
                json_encode(
                    $results
                )
            );
    }

    public function test() {
        $this->data["current_dir"] = $this->input->get('dir');

        $this->load->view('common-start', array(
            'styleIncludes' => array(
                'css/intake.css', 
                'lib/datatables/datatables.css', 
                'lib/chosen-select/chosen.min.css'
            ),
            'scriptIncludes' => array(
                'js/intake.js', 
                'lib/datatables/datatables.js', 
                'lib/chosen-select/chosen.jquery.min.js'
            ),
            'activeModule'   => $this->module->name(),
            'user' => array(
                'username' => $this->rodsuser->getUsername(),
            ),
        ));
        $this->load->view('testview', $this->data);
        $this->load->view('common-end');
    }

    public function getDirsInformation() {
        $directory = $this->input->get('dir');
        $rodsaccount = $this->rodsuser->getRodsAccount();
        $pathStart = $this->pathlibrary->getPathStart($this->config);
        $segments = $this->pathlibrary->getPathSegments($rodsaccount, $pathStart, $directory, $dir);
        $this->pathlibrary->getCurrentLevelAndDepth($this->config, $segments, $head, $level_depth);
        $perms = $this->study->getPermissionsForLevel($level_depth, $segments[0]);

        $offset = $this->input->get('start') ? $this->input->get('start') : 0;
        $limit = $this->input->get('length') ? $this->input->get('length') : 0;
        $search = $this->input->get('search');
        $nextLevelCanSnap = $this->input->get('canSnap') === "1";
        $data = $this->filesystem->getDirsInformation($rodsaccount, $directory, $limit, $offset, $search, $nextLevelCanSnap);

        $columns = array(
            array(
                'db' => 'name', 
                'dt' => 'filename',
                'formatter' => function($d, $row) {
                    $lnktmpl = '<span class="glyphicon glyphicon-%4$s" style="margin-right: 10px;"></span>';
                    $lnktmpl .= '<a href="%1$s/intake/index?dir=%2$s/%3$s">%3$s</a>';
                    $lnk = sprintf(
                        $lnktmpl,
                        htmlentities($this->module->getModuleBase()),
                        htmlentities($this->input->get('dir')),
                        htmlentities($d),
                        htmlentities($this->input->get('glyph'))
                    );
                    return sprintf(
                        '<span class="glyphicon glyphicon-folder"></span>%1$s',
                        $lnk
                    );
                }
            ),
            array(
                'db' => 'size', 
                'dt' => 'size',
                'formatter' => function($d, $row) {
                    return human_filesize(intval(htmlentities($d)));
                }
            ),
            array(
                'db' => 'nfiles',
                'dt' => 'count',
                'formatter' => function($d, $row) {
                    return sprintf(
                        lang('intake_n_files_in_n_dirs'), 
                        $d, 
                        $row['ndirectories']
                    );
                }
            ),
            array(
                'db' => 'created', 
                'dt' => 'created', 
                'formatter' => function($d, $row) {
                    return absoluteTimeWithTooltip($d);
                }
            ),
            array(
                'db' => 'modified', 
                'dt' => 'modified', 
                'formatter' => function($d, $row) {
                    $d = $d === "0" ? $row["created"] : $d;
                    return absoluteTimeWithTooltip($d);
                }
            ),
            array(
                'db' => 'version',
                'dt' => 'version',
                'formatter' => function($d, $row) {
                    if($d) {
                        return sprintf(
                            lang('intake_latest_snapshot_by'),
                            htmlentities($d),
                            relativeTimeWithTooltip(
                                $row["versionTime"], true
                            ),
                            htmlentities($row["versionUser"])
                        );
                    } else {
                        return lang('intake_no_snapshots_text');
                    }
                }
            )
        );

        echo json_encode(
            array(
                "draw"            => $this->input->get('draw') ?
                    intval( $this->input->get('draw') ) :
                    0,
                "recordsTotal"    => intval( $data["total"] ),
                "recordsFiltered" => intval( $data["filtered"] ? $data["filtered"] : $data["total"]),
                "data"            => $this->ssp->data_output( $columns, $data["data"] )
            )
        );
    }

    public function getFilesInformation() {
        $directory = $this->input->get('dir');
        $rodsaccount = $this->rodsuser->getRodsAccount();
        $pathStart = $this->pathlibrary->getPathStart($this->config);
        $segments = $this->pathlibrary->getPathSegments($rodsaccount, $pathStart, $directory, $dir);
        $this->pathlibrary->getCurrentLevelAndDepth($this->config, $segments, $head, $level_depth);
        $perms = $this->study->getPermissionsForLevel($level_depth, $segments[0]);

        $offset = $this->input->get('start') ? $this->input->get('start') : 0;
        $limit = $this->input->get('length') ? $this->input->get('length') : 0;
        $search = $this->input->get('search');

        $data = $this->filesystem->getFilesInformation($rodsaccount, $directory, $limit, $offset, $search);

        $columns = array(
            array(
                'db' => 'file', 
                'dt' => 'filename',
                'formatter' => function($d, $row) {
                    return sprintf(
                        '<span class="glyphicon glyphicon-file" style="margin-right: 10px;"></span>%1$s',
                        htmlentities($d)
                    );
                }
            ),
            array(
                'db' => 'size', 
                'dt' => 'size',
                'formatter' => function($d, $row) {
                    return human_filesize(intval(htmlentities($d)));
                }
            ),
            array(
                'db' => 'created', 
                'dt' => 'created', 
                'formatter' => function($d, $row) {
                    return absoluteTimeWithTooltip($d);
                }
            ),
            array('db' => 'modified', 
                'dt' => 'modified', 
                'formatter' => function($d, $row) {
                    return absoluteTimeWithTooltip($d);
                }
            ),
        );

        echo json_encode(
            array(
                "draw"            => $this->input->get('draw') ?
                    intval( $this->input->get('draw') ) :
                    0,
                "recordsTotal"    => intval( $data["total"] ),
                "recordsFiltered" => intval( $data["filtered"] ? $data["filtered"] : $data["total"]),
                "data"            => $this->ssp->data_output( $columns, $data["data"] )
            )
        );
    }
}