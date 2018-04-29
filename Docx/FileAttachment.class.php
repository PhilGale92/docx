<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 17:59
 */
namespace Docx;
class FileAttachment {
    /**
     * @var string
     */
    protected $_fileName = '';
    /**
     * @var string
     * @Desc Base64 encoding of asset
     */
    protected $_fileData = '';
    /**
     * @var string | int
     * @desc Height specified ( if available)
     */
    protected $_height = 'auto';
    /**
     * @var string | int
     * @desc Width specified ( if available)
     */
    protected $_width = 'auto';
    /**
     * @var null | string
     * @desc Linkup with the relationships xml ( if found )
     */
    protected $_relationLinkupId = null;

    /**
     * FileAttachment constructor.
     * @param $fileName
     * @param $fileData
     */
    public function __construct($fileName, $fileData){
        $this->_fileName = $fileName;
        $this->_fileData = $fileData;
    }
    /**
     * @param $linkupId string
     */
    public function setLinkupId($linkupId){
        $this->_relationLinkupId = $linkupId;
    }
    /**
     * @param $height int
     */
    public function setHeight($height){
        $this->_height = $height;
    }

    /**
     * @param $width int
     */
    public function setWidth($width){
        $this->_width = $width;
    }
}