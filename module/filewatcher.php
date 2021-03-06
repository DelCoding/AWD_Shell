<?php
$GLOBALS['module']['filewatcher']['id'] = "filewatcher";
$GLOBALS['module']['filewatcher']['title'] = "FileWatcher";
$GLOBALS['module']['filewatcher']['js_ontabselected'] = "watcher_init();";
$GLOBALS['module']['filewatcher']['content'] = "<div class='border watcherResult' id=\"msg\" style=\"overFlow-y:scroll; height: 400px\"></div>
<span id=\"clearDog\" style=\"width:120px;\" class=\"button\" onclick=\"clearDog();\">Clear Dog</span>
<span id=\"stopDog\" style=\"width:120px;\" class=\"button\" onclick=\"stopDog();\">Stop Dog</span>
<span id=\"startDog\" style=\"width:120px;\" class=\"button\" onclick=\"startDog();\">Start Dog</span>

<input type='text' id='timer' style='width:34%;outline:none;background:#ffffff;padding:8px;border:1px solid #cccccc;border-radius:8px;color:#000000;'>
<span id=\"setTimer\" style=\"width:120px;\" class=\"button\" onclick=\"setTimer();\">Set Timer</span>
";

class FileWatcher
{
    /**
     * @var array
     */
    protected $_config;
    /**
     * @var string
     */
    protected $_providedPassword;
    /**
     * @var string
     */
    protected $_providedOverallHash;

    /**
     * @param array $options
     */
    public function __construct($options = array())
    {
        $this->readConfig();
    }

    /**
     * @param $configFilename
     * @return FileWatcher
     */
    public function readConfig()
    {

        $this->_config = array(
            'password'              => '',
            'includePaths'          => array('/var/www/html/'),
            'excludeFolderList'     => array('/tmp/'),
            'excludeExtensionList'  => array('pdf'),

            'hashMasterFilename'    => 'FileWatcher.MasterHashes.txt',
            'overwriteMasterFile'   => true,

        );


        if (php_sapi_name() == "cli") {
            $longopts = array(
                'password::',    // Optional value
                'overallHash::', // Optional value
            );
            $options = getopt('', $longopts);

            if (isset($options['password'])) {
                $this->_providedPassword = $options['password'];
            }
            if (isset($options['overallHash'])) {
                $this->_providedOverallHash = $options['overallHash'];
            }
        } else { // not in cli-mode, try to get password from $_GET or $_POST
            if (isset($_POST['password'])) {
                $this->_providedPassword = $_POST['password'];
            } elseif($_GET['password']) {
                $this->_providedPassword = $_GET['password'];
            }

            if (isset($_POST['overallHash'])) {
                $this->_providedOverallHash = $_POST['overallHash'];
            } elseif($_GET['overallHash']) {
                $this->_providedOverallHash = $_GET['overallHash'];
            }
        }

        return $this;
    }

    public function checkNow()
    {

        $this->_checkPassword();

        $currentHashes = $this->_getCurrentHashes();

        $masterHashes = $this->_loadMasterHashes();

        // compare both hash arrays
        $newFiles     = array_diff_key($currentHashes, $masterHashes);
        $deletedFiles = array_diff_key($masterHashes, $currentHashes);

        $changedFiles = array();
        $intersectKeys = array_keys(array_intersect_key($masterHashes, $currentHashes));
        foreach ($intersectKeys as $intersectKey) {
            if ($masterHashes[$intersectKey] != $currentHashes[$intersectKey]) {
                $changedFiles[$intersectKey] = $masterHashes[$intersectKey];
            }
        }

        $overallHash = $this->_calcOverallHash($newFiles, $deletedFiles, $changedFiles);

        if (count($newFiles) > 0 || count($deletedFiles) > 0 || count($changedFiles) > 0 || (!empty($this->_providedOverallHash) && $overallHash != $this->_providedOverallHash)) {

            // save new master hash file if needed
            if ($this->_config['overwriteMasterFile']) {
                $this->_saveMasterHashes($currentHashes);
            }
        } else {
            $a = "a";
        }
        $result = "New files: <br>";
        $result .= "<font color=\"red\">";
        $result .= implode("<br>", array_keys($newFiles));
        $result .= "</font>";
        $result .= "<br>Changed files: <br>";
        $result .= "<font color=\"blue\">";
        $result .= implode("<br>", array_keys($changedFiles));
        $result .= "</font>";
        $result .= "<br>Deleted files: <br>";
        $result .= "<font color=\"green\">";
        $result .= implode("<br>", array_keys($deletedFiles));
        $result .= "</font>";
        return $result;
    }

    /**
     * @return array
     */
    protected function _getCurrentHashes() {
        $currentHashes = array();

        foreach ($this->_config['includePaths'] as $includePath) {

            $iterator = new RecursiveDirectoryIterator($includePath);
            foreach(new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                /** @var $file SplFileInfo */

                // check against excludeFolderList
                if (!$this->_isPathExcluded($file->getPathname(), $this->_config['excludeFolderList'])) {
                    if (!$file->isDir()) {
                        // check against excludeExtensionList
                        $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
                        if (!in_array(strtolower($extension), $this->_config['excludeExtensionList'])) {
                            $hash = sha1_file($file->getPathname());
                            $currentHashes[$file->getPathname()] = $hash;
                        } else {
                            $a = "a";
                        }
                    }
                } else {
                    $a = "a";
                }
            }
        }

        return $currentHashes;
    }

    /**
     * @return FileWatcher
     */
    protected  function _checkPassword()
    {
        if (!empty($this->_config['password']) && $this->_config['password'] != $this->_providedPassword) {
            die('wrong password, exiting now...');
        }

        return $this;
    }

    /**
     * @param array $files
     * @return array
     */
    protected function _createAllHashesOfFilelist(array $files) {
        $list = array();
        foreach ($files as $file) {
            $list[$file] = sha1_file($file);
        }

        return $list;
    }

    /**
     * @param array $files
     */
    protected function _saveMasterHashes(array $files) {
        $fileContent = '';
        foreach ($files as $key => $filepath) {
            $fileContent .= $key.'='.$filepath."\n";
        }

        $fh = fopen($this->_config['hashMasterFilename'], 'w');
        fwrite($fh, $fileContent);
        fclose($fh);
    }

    /**
     * @return array
     */
    protected function _loadMasterHashes() {
        $masterFilePath = $this->_config['hashMasterFilename'];
        if (!file_exists($masterFilePath)) {
            return array();
        }

        $hashes = array();
        $masterFileLines = file($masterFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($masterFileLines as $masterFileLine) {
            list($filepath, $hash) = explode('=', $masterFileLine);
            $hashes[$filepath] = $hash;
        }

        return $hashes;
    }

    /**
     * Build the overallHash over all single file hashes
     *
     * @param array $newFiles
     * @param array $deletedFiles
     * @param array $changedFiles
     * @return string
     */
    protected function _calcOverallHash(array $newFiles, array $deletedFiles, array $changedFiles)
    {
        $merged = array_merge($newFiles, $deletedFiles, $changedFiles);

        return sha1(join(',',$merged));
    }


    /**
     * @param array $newFiles
     * @param array $deletedFiles
     * @param array $changedFiles
     * @return void
     */
    protected function _outputAlert(array $newFiles, array $deletedFiles, array $changedFiles)
    {
        $body = 'new: '.var_export($newFiles, true)."\n".
            'deleted: '.var_export($deletedFiles, true)."\n".
            'changed: '.var_export($changedFiles, true)."\n";
        echo $body;
    }

    /**
     * @param $fullFilename
     * @param array $pathArray
     * @return bool
     */
    protected function _isPathExcluded($fullFilename, array $pathArray) {
        foreach ($pathArray as $path) {
            $path = rtrim($path, '/\\');

            if (strpos($fullFilename, $path) === 0) {
                return true;
            }
        }
        return false;
    }

}

if (!function_exists('watcher')){
    function watcher(){
        $fileWatcher = new FileWatcher();
        $fileWatcher->readConfig();
        return $fileWatcher->checkNow();
    }
}

if(isset($p['watcherDog'])){
    $dog = watcher();
    if($dog!==false) output($dog);
    output('error');
}

?>