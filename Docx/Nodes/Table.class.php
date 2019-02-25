<?php
/**
 * Created by PhpStorm.
 * User: Phil Gale
 * Date: 25/02/2019
 * Time: 12:12
 */
namespace Docx\Nodes;
/**
 * Class Table
 * @package Docx\Nodes
 */
class Table extends Node {
    /**
     * @var bool
     * @desc Toggles behaviour of setting the heading row to use [th] elements
     */
    protected $_useTableHeadings = false;

    /**
     * @var int
     */
    protected $_maxColumnCount = 0 ;

    /**
     * @var array
     */
    protected $_tableGrid =  [];

    /**
     * @var string
     */
    protected $_tableHtml = '';


    /**
     * @param \Docx\Docx $docx
     * @param bool $isDirect
     */
    protected function _extender( $docx, $isDirect ){
        $this->_tableGrid = $this->_drawTableGrid();
        $this->_renderTableGrid(  ) ;
    }

    /**
     * @param string $renderMode
     * @return string
     */
    public function getTableRender($renderMode = 'html'){
        if ($renderMode == 'html'){
            return $this->_tableHtml;
        }
        return '';
    }

    /**
     * @param $cellElement \DOMElement
     * @return string
     */
    protected function _renderCell($cellElement){
        # Run through text runs & hyperlinks
        $cellNodes = $this->_docx->loadNodesFromElement($cellElement, false );
        $ret = '';
        foreach ($cellNodes as $cellNode ) {
            $ret .= $cellNode->render('html');
        }
        return $ret;
    }

    protected function _renderTableGrid(){
        # Stage 7 - Write the HTML (Tokenize where the contents of the table can be inserted)
        $html = '<table class="col_count_' . $this->_maxColumnCount . '">';
        foreach ($this->_tableGrid as $i => $row){
            $headerStr = '';
            $cellTag = 'td';
            if ($this->_useTableHeadings){
                if ($i == 0 ){
                    $headerStr = ' class="headers"';
                    $cellTag = 'th';
                }
            }

            $html .= '<tr' . $headerStr . '>';
            $insertTds = $this->_maxColumnCount - 1;
            foreach ($row as $ii => $cell){

                $colSpanStr = '';
                $colSpanClass = '';
                $subCellMaxSpan = 1 ;
                if (isset($cell[0])){
                    foreach ($cell as $xi => $subCell){
                        if ($subCell['colSpan'] > 1){
                            $insertTds = $insertTds - $subCell['colSpan'] ;
                            if ($subCell['colSpan'] > $subCellMaxSpan) $subCellMaxSpan = $subCell['colSpan'];
                        }
                    }
                }
                if (isset($cell['colSpan'])){
                    if ($cell['colSpan'] > 1){
                        $colSpanStr = ' colspan="' . $cell['colSpan'] . '"';
                        $colSpanClass .= ' has_colspan';
                    }
                    $insertTds = $insertTds - $cell['colSpan'];
                } else $insertTds--;
                if ($subCellMaxSpan > 1 ){
                    $colSpanStr = ' colspan="' . $subCellMaxSpan . '"';
                    $colSpanClass .= ' has_colspan';
                }


                $subCellClassStr = '';
                if (isset($cell[0])) $subCellClassStr = ' has_subcell ';
                $cellHtml = '';

                if (isset($cell[0])){
                    # Sub cell within cell
                    $cellHtml .= '<table>';
                    foreach ($cell as $iii => $subCell){
                        # Dont render an empty subCell
                        $contentCheck = $this->_docx->getXPath()->query("w:p/w:r", $subCell['dom']);
                        if ($contentCheck->length == 0)
                            continue;
                        $cellHtml .= '<tr class="vmerge merge_' . $ii  . '_' . $iii . '"><td>';
                            $cellHtml .= $this->_renderCell($subCell['dom']);
                        $cellHtml .= '</td></tr>';
                    }
                    $cellHtml .= '</table>';
                } else {
                    # Standard cell
                    $cellHtml .= $this->_renderCell($cell['dom']);
                }
                $html .=  '<' . $cellTag . ' class="col_' . ($ii + 1) . $subCellClassStr . $colSpanClass .  '" '
                    . $colSpanStr . '>';
                $html .= $cellHtml;
                $html .= '</' . $cellTag . '>';
            }

            for ($loopI = 0; $loopI <= $insertTds; $loopI++){
                $html .= '<td></td>';
            }

            $html .= '</tr>';
        }
        $html .= '</table>';

        $this->_tableHtml = $html;
    }


    /**
     * @return array
     * @desc This method constructs the tabular grid
     */
    private function _drawTableGrid(){
        $this->_maxColumnCount = $this->_docx->getXPath()->query("w:tblGrid/w:gridCol", $this->_domElement)->length;

        # Stage 2 - Start a loop for each table row + cell to compile cell structure info
        $tableArr = [];
        $tableRowsDom = $this->_docx->getXPath()->query("w:tr", $this->_domElement);
        foreach ($tableRowsDom as $i => $tableRowDom){
            $rowCellsDom = $this->_docx->getXPath()->query("w:tc", $tableRowDom);
            foreach ($rowCellsDom as $ii => $cellDom){
                $vMergeRestartAttr = false;

                # Vertical Merge
                if ($this->_docx->getXPath()->query("w:tcPr/w:vMerge", $cellDom)->length == 1){
                    $cellVerticalMerge = true;
                    $vMergeDomAttr = $this->_docx->getXPath()->query("w:tcPr/w:vMerge", $cellDom)->item(0)->attributes;
                    $baseAttr = $vMergeDomAttr->item(0);
                    if (isset($baseAttr->nodeValue)){
                        if ($vMergeDomAttr->item(0)->nodeValue == 'restart'){
                            $vMergeRestartAttr = true;
                        }
                    }
                } else
                    $cellVerticalMerge = false;

                # Cell Colspan
                $gridSpanDom = $this->_docx->getXPath()->query("w:tcPr/w:gridSpan", $cellDom);
                $colSpan = 1;
                if ($gridSpanDom->length != 0)
                    $colSpan = (int) $gridSpanDom->item(0)->getAttribute('w:val');

                if (!isset($tableArr[$i])) $tableArr[$i] = array();

                $tableArr[$i][$ii] = array(
                    'dom' => $cellDom,
                    'verticalMergeRestartAttr' => $vMergeRestartAttr,
                    'verticalMerge' => $cellVerticalMerge,
                    'colSpan' => $colSpan
                );

            }
        }

        # Stage 3 - Compile the vertical merge cells & apply the colspan variable to vertical merging
        $vMergeIndex = array();
        $vMergeRestartCache = array();
        foreach ($tableArr as $i => $tableRow){
            foreach ($tableRow as $ii => $tableCell){
                if ($tableCell['verticalMergeRestartAttr'] == true)
                    $vMergeRestartCache[$i] = $tableCell['verticalMergeRestartAttr'];
                $vMergeIndex[$i][$ii] = $tableCell['verticalMerge'];
                if ($tableCell['colSpan']){
                    for ($loopI = $ii; $loopI < ($tableCell['colSpan'] - $ii); $loopI++){
                        $vMergeIndex[$i][$loopI] = $tableCell['verticalMerge'];
                    }
                }
            }
        }

        # Stage 4 - Compile the TRUE/FALSE declarations into a count of Td's
        # Create the required height caches, to help track vertical merging
        $mergeCache = array();
        for ($i = 0; $i < $this->_maxColumnCount; $i++)
            $mergeCache[$i] = null;

        $vMergeIndex = array_reverse($vMergeIndex, true);
        $verticalMergingColCounts = array();
        foreach ($vMergeIndex as $i => $tableRow){
            $vmergeRestart = false;
            if (isset($vMergeRestartCache[$i])) {
                $vmergeRestart = $vMergeRestartCache[$i];
            }
            foreach ($tableRow as $ii => $tableCellIsVMerge){
                if ($vmergeRestart == true){
                    $verticalMergingColCounts[$i ][$ii ] = $mergeCache[$ii] ;
                    $mergeCache[$ii] = 1;
                } else {
                    if ($tableCellIsVMerge){
                        if ($mergeCache[$ii] != null){
                            $mergeCache[$ii]++;
                        } else {
                            $mergeCache[$ii] = 1;
                        }
                        if (!$vMergeIndex[$i - 1][$ii]) $verticalMergingColCounts[$i][$ii] = $mergeCache[$ii];
                    } else {
                        $mergeCache[$ii] = 1;
                        $verticalMergingColCounts[$i][$ii] = 1;
                    }
                }
            }
        }

        $verticalMergingColCounts = array_reverse($verticalMergingColCounts, true);
        # Stage 5 - Invert how the cell heights are stored, due to how HTML handles tables differently then docx structures
        # - Html has small cells merged vertically, html has large cells with subcells
        $skipRow = 0;
        foreach ($verticalMergingColCounts as $i => &$row){
            $currentMaxHeight = 1;
            foreach ($row as $ii => $cellHeight){
                if ($cellHeight > $currentMaxHeight){
                    $currentMaxHeight = $cellHeight;
                }
            }
            if ($skipRow > 0){ $skipRow--; unset($verticalMergingColCounts[$i]); continue; }
            if ($skipRow == 0)
                $skipRow = $currentMaxHeight - 1;

            foreach ($row as $ii => &$cell){
                if ($cell >= $currentMaxHeight)
                    $cell = 1;
                else
                    $cell = $currentMaxHeight;
            }
        }
        # Stage 6 - Compile the Td's into a single tabular array using the $verticalMergingColCounts
        $renderTable = array();
        foreach ($verticalMergingColCounts as $i => $row){
            $skipCount = -1;
            foreach ($row as $ii => $cellHeight){
                if ($skipCount > 0){
                    $skipCount--;
                    continue;
                }

                if ($cellHeight == 1)
                    @$renderTable[$i][$ii] = $tableArr[$i][$ii];
                else {
                    for ($cellInt = 1; $cellInt <= $cellHeight; $cellInt++){
                        @$renderTable[$i][$ii][] = $tableArr[$i + $cellInt - 1][$ii];
                    }
                }
            }
        }
        # Stage 6b - Strip unwanted cells out
        foreach ($renderTable as $i => $rowXV ) {
            $rowSpacer = 0 ;
            $bColSpansUsed = false ;
            $startI = 0 ;
            $mergedCellCount = 0 ;
            foreach ($rowXV as $ii => $cellXV ) {
                if ($cellXV['colSpan'] > 1) $bColSpansUsed = true;
                $rowSpacer += $cellXV['colSpan'];
                if ($cellXV != null ) $startI = $ii;
            }
            if ($bColSpansUsed ) {
                $maxI = count($rowXV) + $mergedCellCount;
                for ($loopI = $maxI; $loopI > ($maxI - $rowSpacer); $loopI--) {
                    if ($startI == $loopI ) break ;
                    unset($renderTable[$i][$loopI]);
                }
            }
        }
        return $renderTable;
    }


}