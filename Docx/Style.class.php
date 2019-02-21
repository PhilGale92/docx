<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 30/04/2018
 * Time: 12:11
 */
namespace Docx;
class Style {

    /**
     * @var int
     */
    protected $_listLevel = 0;

    /**
     * @var string
     */
    protected $_wordStyleName = '';

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

}