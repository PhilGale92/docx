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
    protected $_wordStyleName = '';

    /**
     * @var bool
     */
    protected $_flagSelfGenerateHtmlId = false;

    /**
     * Style constructor.
     * @param $wordStyleName string
     */
    public function __construct($wordStyleName){
        $this->_wordStyleName = $wordStyleName ;
    }

    /**
     * @param $styleName string
     * @return Style
     */
    public static function getFromStyleName($styleName){
        return new Style($styleName);
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

}