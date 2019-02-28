<?php
/**
 * Created by PhpStorm.
 * User: Phil Gale
 * Date: 22/02/2019
 * Time: 10:25
 */
namespace PhilGale92Docx\Nodes;
use PhilGale92Docx\Docx;
use PhilGale92Docx\FileAttachment;

/**
 * Class ProcessedRun
 * @package PhilGale92Docx\Nodes
 */
class ProcessedRun {
    /**
     * @var bool
     */
    protected $_attribTabbed = false;
    /**
     * @var bool
     */
    protected $_attribUnderline = false;
    /**
     * @var bool
     */
    protected $_attribSubScript = false;
    /**
     * @var bool
     */
    protected $_attribSupScript = false;
    /**
     * @var bool
     */
    protected $_attribBold = false;
    /**
     * @var bool
     */
    protected $_attribItalic = false ;

    /**
     * @var null | FileAttachment
     */
    protected $_imageContent = null;
    /**
     * @var string
     */
    protected $_textContent = '';
    /**
     * @var null | string
     */
    protected $_hyperLinkHref = null;
    /**
     * @var bool
     */
    protected $_hyperLinkAutoBehaviour = false;

    /**
     * @param $file FileAttachment
     */
    public function setImageContent($file){
        $this->_imageContent = $file;
    }

    /**
     * @param $text string
     */
    public function setTextContent($text){
        $this->_textContent = $text;
    }

    /**
     * @param $toggle bool
     */
    public function setHyperLinkAutoBehaviour($toggle){
        $this->_hyperLinkAutoBehaviour = $toggle;
    }

    /**
     * @param $href string
     */
    public function setHyperLinkHref($href){
        $this->_hyperLinkHref = $href ;
    }


    /**
     * @param $toggle bool
     */
    public function setAttributeBold($toggle){
        $this->_attribBold = $toggle;
    }
    /**
     * @param $toggle bool
     */
    public function setAttributeItalic($toggle){
        $this->_attribItalic = $toggle;
    }
    /**
     * @param $toggle bool
     */
    public function setAttributeSubScript($toggle){
        $this->_attribSubScript = $toggle;
    }
    /**
     * @param $toggle bool
     */
    public function setAttributeSupScript($toggle){
        $this->_attribSupScript = $toggle;
    }
    /**
     * @param $toggle bool
     */
    public function setAttributeUnderline($toggle){
        $this->_attribUnderline = $toggle;
    }

    /**
     * @param $toggle bool
     */
    public function setAttributeTabbed($toggle){
        $this->_attribTabbed = $toggle;
    }

    /**
     * @return string
     */
    public function getRawText(){
        return $this->_textContent;
    }

    /**
     * @param string $renderMode
     * @return array ['prepend', 'content', 'append']
     */
    public function getProcessedText( $renderMode = Docx::RENDER_MODE_HTML){
        $rawText = $this->getRawText();

        /*
         * If we're writing to html, ensure the content is escaped from the tags!
         */
        if ($renderMode == Docx::RENDER_MODE_HTML){
            $rawText = htmlentities($rawText);
        }

        if ($this->_imageContent != null){
            $rawText .= $this->_imageContent->getImageHtmlTag();
        }
        $runPrepend  = $runAppend = '';
        if ($this->_hyperLinkHref != null ) {
            $runPrepend = '<a href="' . $this->_hyperLinkHref . '">' . $runPrepend;
            $runAppend .= '</a>';
        }

        if ($this->_attribBold){ $runPrepend = '<b>' . $runPrepend; $runAppend .= '</b>';}
        if ($this->_attribUnderline){ $runPrepend = '<u>' . $runPrepend; $runAppend .= '</u>';}
        if ($this->_attribItalic){ $runPrepend = '<i>' . $runPrepend; $runAppend .= '</i>';}
        if ($this->_attribSupScript){ $runPrepend = '<sup>' . $runPrepend; $runAppend .= '</sup>';}
        if ($this->_attribSubScript){ $runPrepend = '<sub>' . $runPrepend; $runAppend .= '</sub>';}
        if ($this->_attribTabbed) $rawText = '<span class="tab"></span>' . $rawText;

        return [
            'prepend' => $runPrepend,
             'content' =>  $rawText,
            'append' =>  $runAppend
        ];
    }

    /**
     * @return bool
     */
    public function getAttributeItalic(){
        return $this->_attribItalic;
    }
    /**
     * @return bool
     */
    public function getAttributeSupScript(){
        return $this->_attribSupScript;
    }
    /**
     * @return bool
     */
    public function getAttributeSubScript(){
        return $this->_attribSubScript;
    }
    /**
     * @return bool
     */
    public function getAttributeBold(){
        return $this->_attribBold;
    }
    /**
     * @return bool
     */
    public function getAttributeUnderline(){
        return $this->_attribUnderline;
    }
    /**
     * @return bool
     */
    public function getAttributeTabbed(){
        return $this->_attribTabbed;
    }

}