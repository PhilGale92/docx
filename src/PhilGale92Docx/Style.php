<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 30/04/2018
 * Time: 12:11
 */
namespace PhilGale92Docx;
/**
 * Class Style
 * @package PhilGale92Docx
 */
class Style {
    /**
     * @var string
     */
    protected $_htmlTagName = 'p';
    /**
     * @var string
     */
    protected $_htmlCssClass = '';

    /**
     * @var int
     */
    protected $_listLevel = 0;

    /**
     * @var string
     */
    protected $_wordStyleId = '';

    /**
     * @var bool
     */
    protected $_flagSelfGenerateHtmlId = false;

    /**
     * @var bool
     * @desc Set to TRUE to remove styles from the standard html output,
     * and move into the metaData attribute
     */
    protected $_isMetaDataStyle = false;

    /**
     * @var string
     * @desc Used to set the render mode for any metaDataAttributes attached to this style
     * set to 'html' or 'plain'
     */
    protected $_metaDataRenderMode = Docx::RENDER_MODE_HTML;

    /**
     * @param $styleName string
     * @param $docx Docx
     * @return Style
     */
    public static function getFromStyleName($styleName, $docx){
        return $docx->getDeclaredStyleFromId($styleName);
    }

    /**
     * @return int
     */
    public function getListLevel(){
        return $this->_listLevel;
    }

    /**
     * @return bool
     * @desc If true, it means the htmlId attribute, should be generated using the raw text contents
     */
    public function getFlagGenerateHtmlId(){
        return $this->_flagSelfGenerateHtmlId;
    }

    /**
     * @return string
     */
    public function getHtmlClass(){
        return $this->_htmlCssClass;
    }

    /**
     * @return string
     */
    public function getHtmlTag(){
        return $this->_htmlTagName;
    }
    /**
     * @return string
     */
    public function getStyleId(){
        return $this->_wordStyleId;
    }
    /**
     * @return bool
     */
    public function getIsMetaData(){
        return $this->_isMetaDataStyle;
    }

    /**
     * @return string
     */
    public function getMetaDataRenderMode(){
        return $this->_metaDataRenderMode;
    }

    /**
     * @param $styleId string
     * @return $this
     */
    public function setStyleId($styleId){
        $this->_wordStyleId = $styleId;
        return $this;
    }

    /**
     * @param $toggle bool
     * @return $this
     */
    public function setIsMetaData($toggle){
        $this->_isMetaDataStyle = $toggle;
        return $this;
    }

    /**
     * @param string $renderMode string
     * @return $this
     */
    public function setMetaDataRenderMode($renderMode = Docx::RENDER_MODE_HTML){
        $this->_metaDataRenderMode = $renderMode;
        return $this;
    }

    /**
     * @param $toggle bool
     * @return $this
     */
    public function setFlagGenerateHtmlId($toggle){
        $this->_flagSelfGenerateHtmlId = $toggle;
        return $this;
    }




}