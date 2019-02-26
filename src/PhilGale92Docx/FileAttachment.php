<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 17:59
 */
namespace PhilGale92Docx;
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
     * @var array
     * @desc Stores the data prepared for rendering, as processed by RunDrawingLib
     * Format of :
'type' => 'image',
'name' => $imageData->getFileName(),
'h' => $h,
'w' => $w,
'data' => $imageData->getFileData()
     */
    protected $_renderDataArr = [];

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

    /**
     * @param $renderArr array
     */
    public function setRunData($renderArr){
        $this->_renderDataArr = $renderArr;
    }
    /**
     * @return string|null
     */
    public function getLinkupId(){
        return $this->_relationLinkupId;
    }

    /**
     * @return int|string
     */
    public function getWidth(){
        return $this->_width;
    }

    /**
     * @return int|string
     */
    public function getHeight(){
        return $this->_height;
    }

    /**
     * @return string
     */
    public function getFileName(){
        return $this->_fileName;
    }

    /**
     * @return string
     */
    public function getFileData(){
        return $this->_fileData;
    }

    /**
     * @return array
     */
    public function getRenderFileData(){
        return $this->_renderDataArr;
    }

    /**
     * @return string
     */
    public function getImageHtmlTag(){
        /*
         * get basic image extension from the file name...
         */
        $imageInfo = explode(".", $this->_renderDataArr['name']);

        /*
         * Construct tag using attributes set from the Run-Image handler (RunDrawingLib)
         */
        $ret = '<img alt=""'
            . ' width="' . $this->_renderDataArr['w'] . '" '
            . ' height="' . $this->_renderDataArr['h'] . '" '
            . ' title="' . $imageInfo[0] . '" '
            . ' src="data:image/' . $imageInfo[1] . ';base64,' . $this->_renderDataArr['data'] . '" />'
        ;
        return $ret ;
    }

}