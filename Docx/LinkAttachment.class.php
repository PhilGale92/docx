<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 18:39
 */
namespace Docx;
class LinkAttachment {
    /**
     * @var string
     */
    protected $_link = '';
    /**
     * @var string
     */
    protected $_linkupId = '';

    /**
     * LinkAttachment constructor.
     * @param $relationshipId string
     * @param $link string
     */
    public function __construct($relationshipId, $link){
        $this->_linkupId = $relationshipId;
        $this->_link = $link;
    }

    /**
     * @return string
     */
    public function getLinkupId(){
        return $this->_linkupId;
    }

    /**
     * @return string
     */
    public function getLink(){
        return $this->_link;
    }

}