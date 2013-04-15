<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */


/**
 * Build various graphs using Google Charts
 *
 * This is taken from CRM_Utils_OpenFlashChart in order to build
 * a stacked bar chart
 *
 * At some point we need to reintegrate into CRM_Utils_OpenFlashChart
 * However CRM_Utils_OpenFlashChart should ideally be restructured to use OOP as
 * extension currently difficult
 */
class CRM_Extendedreport_Form_Report_Charts_GoogleCharts {
  /**
     * colours.
     * @var array
     * @static
     */

  function buildDataArray(&$params, $chart) {
    $chartData = array();
    if ($chart && is_array($params) && ! empty($params)) {
      $chartInstance = new $chart($params);
      $chartInstance->buildChart();
      $chartObj = $chartInstance->getChart();
      $chartData = array();
      if ($chartObj) {
        // calculate chart size.
        $xSize = CRM_Utils_Array::value('xSize', $params, 400);
        $ySize = CRM_Utils_Array::value('ySize', $params, 300);
        if ($chart == 'barChart') {
          $ySize = CRM_Utils_Array::value('ySize', $params, 250);
          $xSize = 60 * count($params['values']);
          //hack to show tooltip.
          if ($xSize < 200) {
            $xSize = (count($params['values']) > 1) ? 100 * count($params['values']) : 170;
          }
          elseif ($xSize > 600 && count($params['values']) > 1) {
            $xSize = (count($params['values']) + 400 / count($params['values'])) * count($params['values']);
          }
        }

        // generate unique id for this chart instance
        $uniqueId = md5(uniqid(rand(), TRUE));

        $chartData["chart_{$uniqueId}"]['size'] = array(
          'xSize' => $xSize,
          'ySize' => $ySize
        );
        $chartData["chart_{$uniqueId}"]['object'] = $chartObj;

        // assign chart data to template
        $template = CRM_Core_Smarty::singleton();
        $template->assign('uniqueId', $uniqueId);
        $template->assign("openFlashChartData", json_encode($chartData));
      }
    }
    return $chartData;
  }
}
/**
   * Base class for all non-specific actions
   *
   * @author eileen
   *
   */
class chart {
  protected $_colours = array(
    "#C3CC38",
    "#C8B935",
    "#CEA632",
    "#D3932F",
    "#D9802C",
    "#FA6900",
    "#DC9B57",
    "#F78F01",
    "#5AB56E",
    "#6F8069",
    "#C92200",
    "#EB6C5C"
  );
  protected $chartTitle;
  protected $values = array();
  protected $tooltip = array();
  protected $chart = null;
  protected $chartElement = null;
  protected $onClickFunName = null;

  function __construct($params) {
    $chart = NULL;
    if (empty($params)) {
      return $chart;
    }
    $this->values = CRM_Utils_Array::value('values', $params);
    if (! is_array($this->values) || empty($this->values)) {
      return $chart;
    }
    $this->chartTitle = CRM_Utils_Array::value('title', $params);
    $this->createChartElement();
    $this->chartElement->set_colours($this->_colours);
    $this->setToolTip(CRM_Utils_Array::value('tip', $params));
    $this->onClickFunName = CRM_Utils_Array::value('on_click_fun_name', $params);
  }

  /**
   *
   * Set the tool tip
   * @param string $tip
   */
  function setToolTip($tip){
    if($tip){
      $this->chartElement->set_tooltip($tip);
      return;
    }
    else{
      $config = CRM_Core_Config::singleton();
      $symbol = $config->defaultCurrencySymbol;
      $this->chartElement->set_tooltip("$symbol #val#");
    }
  }

  /**
     * Add main element
     */
  function addChartElement() {
  }
  /**
 * Steps to pull together chart
 */
  function buildChart() {
    $this->chart = new open_flash_chart();
    $title = new title($this->chartTitle);
    $this->chart->set_title($title);
    // add bar element to chart.
    $this->chart->add_element($this->chartElement);
  }

  /**
     *
     * @return chart object
     */
  function getChart() {
    return $this->chart;
  }
}

/**
   * Base class for all bar chart actions
   *
   * @author eileen
   *
   */


  /**
   * Set maximum Y value & steps based on the highest value in the array plus some rounding
   * @param array $values
   *
   * On bar this values array will be the YValues array. For stack it will be the sum of the
   * relevant values
   */
  function setYMaxYSteps($values){
    // calculate max scale for graph.
    $this->yMax = ceil(max($values));
    if ($mod = $this->yMax % (str_pad(5, strlen($this->yMax) - 1, 0))) {
      $this->yMax += str_pad(5, strlen($this->yMax) - 1, 0) - $mod;
    }
    $this->ySteps = $this->yMax / 5;
  }

  /**
     * Add main element
     */
  function createChartElement() {
    $this->chartElement = new bar_glass();
    $this->chartElement->set_values($this->yValues);
    $this->setYMaxYSteps($this->yValues);
  }
  /**
 * (non-PHPdoc)
 * @see chart::buildChart()
 */
  function buildChart() {
    parent::buildChart();
    $this->buildxyAxis();
    $this->chart->set_x_axis($this->xAxis);
    $this->chart->add_y_axis($this->yAxis);
    if($this->tagPercent && !empty($this->tags)){
      $this->chart->add_element( $this->tags );
    }
  }

  /**
 * build x & y axis
 */
  function buildxyAxis() {
    // add x axis legend.
    if ($this->xAxisName) {
      $xLegend = new x_legend($this->xAxisName);
      $xLegend->set_style("{font-size: 13px; color:#000000; font-family: Verdana; text-align: center;}");
      $this->chart->set_x_legend($xLegend);
    }

    // add y axis legend.
    if ($this->yAxisName) {
      $yLegend = new y_legend($this->yAxisName);
      $yLegend->set_style("{font-size: 13px; color:#000000; font-family: Verdana; text-align: center;}");
      $this->chart->set_y_legend($yLegend);
    }
    // create x axis label obj. @todo - this the setting of labels to xlabels ma
    $xLabels = $this->setXLabels();

    // set angle for labels.
    if ($this->xlabelAngle) {
      $xLabels->rotate($this->xlabelAngle);
    }

    // create x axis obj.
    $this->xAxis = new x_axis();
    $this->xAxis->set_labels($xLabels);

    //create y axis and set range.
    $this->yAxis = new y_axis();
    $this->yAxis->set_range($this->yMin, $this->yMax, $this->ySteps);
  }

  /**
   *
   * Set the xLabels
   * @return object x_axis_labels
   */
  function setXLabels(){
    $xLabels = new x_axis_labels();
    $xLabels->set_labels($this->xValues);
    return $xLabels;
  }
