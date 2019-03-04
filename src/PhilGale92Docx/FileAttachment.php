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
     * @var int
     * @desc Stores the attribute for the crop rect, from the top of the base image
     */
    protected $_cropAttributeTop = 0;
    /**
     * @var int
     * @desc Stores the attribute for the crop rect, from the bottom of the base image
     */
    protected $_cropAttributeBottom = 0;
    /**
     * @var int
     * @desc SStores the attribute for the crop rect, from the left of the base image
     */
    protected $_cropAttributeLeft = 0;
    /**
     * @var int
     * @desc Stores the attribute for the crop rect, from the right of the base image
     */
    protected $_cropAttributeRight = 0;
    /**
     * @var bool
     * @desc Tracks if crop attributes have been used for this drawing
     */
    protected $_hasCropApplied = false;

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

    /**
     * @param $cropAttr int
     */
    public function setCropTop($cropAttr){
        $this->_cropAttributeTop = $cropAttr;
        if (is_numeric($cropAttr) && $cropAttr > 0 ) $this->_hasCropApplied = true ;
    }

    /**
     * @param $cropAttr int
     */
    public function setCropBottom($cropAttr){
        $this->_cropAttributeBottom = $cropAttr;
        if (is_numeric($cropAttr) && $cropAttr > 0 ) $this->_hasCropApplied = true ;
    }

    /**
     * @param $cropAttr int
     */
    public function setCropLeft($cropAttr){
        $this->_cropAttributeLeft = $cropAttr;
        if (is_numeric($cropAttr) && $cropAttr > 0 ) $this->_hasCropApplied = true ;
    }

    /**
     * @param $cropAttr int
     */
    public function setCropRight($cropAttr){
        $this->_cropAttributeRight = $cropAttr;
        if (is_numeric($cropAttr) && $cropAttr > 0 ) $this->_hasCropApplied = true ;
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
     * @return bool
     */
    public function getHasBeenCropped(){
        return $this->_hasCropApplied;
    }

    /**
     * @return int
     */
    public function getCropAttributeTop(){
        return $this->_cropAttributeTop;
    }

    /**
     * @return int
     */
    public function getCropAttributeLeft(){
        return $this->_cropAttributeLeft;
    }

    /**
     * @return int
     */
    public function getCropAttributeRight(){
        return $this->_cropAttributeRight;
    }

    /**
     * @return int
     */
    public function getCropAttributeBottom(){
        return $this->_cropAttributeBottom;
    }

    /**
     * @return string
     */
    public function getImageHtmlTag(){
        /*
         * get basic image extension from the file name...
         */
        $imageInfo = explode(".", $this->_fileName);

        /*
         * Construct tag using attributes set from the Run-Image handler (RunDrawingLib)
         */
        $ret = '<img alt=""'
            . ' width="' . $this->_width . '" '
            . ' height="' . $this->_height . '" '
            . ' title="' . $imageInfo[0] . '" '
            . ' src="data:image/' . $imageInfo[1] . ';base64,' . $this->_fileData . '" />'
        ;
        return $ret ;
    }

}