<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Filesystem model
 *
 * @package    Yoda
 * @copyright  Copyright (c) 2017-2019, Utrecht University. All rights reserved.
 * @license    GPLv3, see LICENSE.
 */
class Filesystem extends CI_Model {

    var $CI = NULL;

    public function __construct()
    {
        parent::__construct();
        $this->CI =& get_instance();
    }

    /**
     * Read a file from iRODS.
     *
     * @param $rodsaccount
     * @param $path
     * @return mixed
     */
    function read($rodsaccount, $file)
    {
        $fileContent = '';

        try {
            $file = new ProdsFile($rodsaccount, $file);
            $file->open("r");

            // Grab the file content.
            while ($str = $file->read(4096)) {
                $fileContent .= $str;
            }

            // Close the file pointer.
            $file->close();

            return $fileContent;

        } catch(RODSException $e) {
            return false;
        }
    }

    /**
     * Download a file from iRODS.
     *
     * @param $rodsaccount
     * @param $path
     * @return mixed
     */
    function download($rodsaccount, $file)
    {
        // Close session to allow other pages to continue.
        session_write_close();

        // Set locale for multibyte characters.
        setlocale(LC_ALL, "en_US.UTF-8");

        // Set headers to force download.
        $filename = basename($file);
        header('Content-Type: application/octet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // Try to open file from iRODS.
        try {
            $file = new ProdsFile($rodsaccount, $file);
            $file->open("r");
        } catch(RODSException $e) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        // Serve the file content.
        try {
            // Determine file size.
            $size = $file->seek(0, SEEK_END);
            header("Content-Length: " . $size);
            $file->rewind();

            // Grab the file content.
            while ($buffer = $file->read(16*1024)) {
                echo $buffer;
                ob_flush();
            }

            // Close the file pointer.
            $file->close();
        } catch(RODSException $e) {
            header("HTTP/1.0 500 Internal Server Error");
            exit;
        }
    }

    /**
     * Upload a file to iRODS.
     *
     * @param $rodsaccount
     * @param $path
     * @return mixed
     */
    function upload($rodsaccount, $path, $file)
    {
        try {
            $tmpFile = $file["tmp_name"];

            // Check file size.
            $size = filesize($tmpFile);
            $maxSize = 100 * 1024 * 1024;
            if ($size > $maxSize) {
                $output = array(
                    'status' => 'ERROR',
                    'statusInfo' => 'File exceeds size limit'
                );
                return $output;
            }

            // Upload file.
            $path = $path . "/" . $file["name"];
            $fd = fopen($tmpFile, "r");

            // Only fread file if not empty.
            if ($size > 0) {
                $content = fread($fd, $size);
            } else {
                $content = "";
            }

            $this->write($rodsaccount, $path, $content);
            fclose($fd);

            $output = array(
                'status' => 'OK',
                'statusInfo' => ''
            );
            return $output;
        } catch(RODSException $e) {
            if ($e->getCodeAbbr() == "OVERWRITE_WITHOUT_FORCE_FLAG") {
                $output = array(
                    'status' => 'ERROR',
                    'statusInfo' => 'File already exists'
                );
                return $output;
            } else {
                $output = array(
                    'status' => 'ERROR',
                    'statusInfo' => 'Upload failed'
                );
                return $output;
           }
        }
    }

    /**
     * Write a file to iRODS.
     *
     * @param $rodsaccount
     * @param $path
     * @param $content
     */
    function write($rodsaccount, $path, $content)
    {
        $file = new ProdsFile($rodsaccount, $path);
        $file->open("w+", $rodsaccount->default_resc);
        $file->write($content);
        $file->close();
        return true;
    }

    /**
     * Delete a file from iRODS.
     *
     * @param $rodsaccount
     * @param $path
     * @param $force
     * @return boolean
     */
    function delete($rodsaccount, $path, $force = false)
    {
        $file = new ProdsFile($rodsaccount, $path);
        $file->unlink($rodsaccount->default_resc, $force);
        return true;
    }

    /**
     * List the locks on a folder.
     *
     * @param $iRodsAccount
     * @param $folder
     * @param $offset
     * @param $limit
     * @return array
     */
    function listLocks($iRodsAccount, $folder, $offset = 0, $limit = 10)
    {
        $output = array();

        $ruleBody = <<<'RULE'
myRule {
    *l = int(*limit);
    *o = int(*offset);

    iiListLocks(*path, *o, *l, *result, *status, *statusInfo);
}
RULE;
        try {
            $rule = new ProdsRule(
                $iRodsAccount,
                $ruleBody,
                array(
                    "*path" => $folder,
                    "*offset" => $offset,
                    "*limit" => $limit

                ),
                array("*result", "*status", "*statusInfo")
            );

            $ruleResult = $rule->execute();
            $output['*result'] = json_decode($ruleResult['*result'], true);
            $output['*status'] = $ruleResult['*status'];
            $output['*statusInfo'] = $ruleResult['*statusInfo'];

            return $output;

        } catch(RODSException $e) {
            return array();
        }

        return array();
    }
}
