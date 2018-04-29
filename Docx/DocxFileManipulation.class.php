<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 16:48
 */
namespace Docx;
abstract class DocxFileManipulation {
    private $_baseUri = '';
    private $_baseFileName = '';

    /**
     * DocxFileManipulation constructor.
     * @param $fileUri
     * @param string | null $fileName ( Not used, legacy arg)
     */
    public function __construct($fileUri, $fileName = '')
    {
        $this->_baseFileName = $fileName;
        $this->_baseUri = $fileUri;
        $this->_extractArchives();

    }

    /**
     * Unzip and track the useful files
     */
    private function _extractArchives(){
        var_dump($this);die;
    }
}