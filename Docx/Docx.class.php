<?php
/**
 * Created by PhpStorm.
 * User: philg
 * Date: 29/04/2018
 * Time: 16:47
 */
namespace Docx ;
class Docx extends DocxFileManipulation {
    /**
     * @var string
     */
    public $html = '';
    /**
     * @var bool
     * @desc Track if import has run, so we can streamline required arguments
     */
    protected $_bHasImported = false ;
    /**
     * @var bool
     * @desc Track if rendering has occured
     */
    protected $_bHasRendered = false ;

    /**
     * @return $this
     * @desc Processes the Object, extracting all the structured data from the base docx file
     */
    public function import(){
        if ($this->_bHasImported) return $this;
        $this->_bHasImported = true;
        return $this;
    }

    /**
     * @param string $renderViewType
     * @return $this
     */
    public function render($renderViewType = 'html'){
        if ($this->_bHasRendered) return $this ;
        if (!$this->_bHasImported) $this->import();
        $this->_bHasRendered = true ;

        return $this;

    }
}