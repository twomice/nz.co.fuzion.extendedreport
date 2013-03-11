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

require_once 'packages/OpenFlashChart/php-ofc-library/open-flash-chart.php';

/**
 * Build various graphs using Open Flash Chart library.
 *
 * This is taken from CRM_Utils_OpenFlashChart in order to build
 * a stacked bar chart
 *
 * At some point we need to reintegrate into CRM_Utils_OpenFlashChart
 * However CRM_Utils_OpenFlashChart should ideally be restructured to use OOP as
 * extension currently difficult
 */
class CRM_Extendedreport_Form_Report_OpenFlashChart {
  /**
     * colours.
     * @var array
     * @static
     */


  /**
     * Build The Pie Gharph.
     *
     * @param  array  $params  assoc array of name/value pairs
     *
     * @return object $chart   object of open flash chart.
     * @static
     */
  static function chart($rows, $chart, $interval) {
    $chartData = array();

    switch ($interval) {
      case 'Month':
        foreach ($rows['receive_date'] as $key => $val) {
          list ($year, $month) = explode('-', $val);
          $graph[substr($rows['Month'][$key], 0, 3) . ' ' . $year] = $rows['value'][$key];
        }

        $chartData = array(
          'values' => $graph,
          'legend' => ts('Monthly Contribution Summary')
        );
        break;

      case 'Quarter':
        foreach ($rows['receive_date'] as $key => $val) {
          list ($year, $month) = explode('-', $val);
          $graph['Quarter ' . $rows['Quarter'][$key] . ' of ' . $year] = $rows['value'][$key];
        }

        $chartData = array(
          'values' => $graph,
          'legend' => ts('Quarterly Contribution Summary')
        );
        break;

      case 'Week':
        foreach ($rows['receive_date'] as $key => $val) {
          list ($year, $month) = explode('-', $val);
          $graph['Week ' . $rows['Week'][$key] . ' of ' . $year] = $rows['value'][$key];
        }

        $chartData = array(
          'values' => $graph,
          'legend' => ts('Weekly Contribution Summary')
        );
        break;

      case 'Year':
        foreach ($rows['receive_date'] as $key => $val) {
          list ($year, $month) = explode('-', $val);
          $graph[$year] = $rows['value'][$key];
        }
        $chartData = array(
          'values' => $graph,
          'legend' => ts('Yearly Contribution Summary')
        );
        break;
    }

    // rotate the x labels.
    $chartData['xLabelAngle'] = CRM_Utils_Array::value('xLabelAngle', $rows, 20);
    if (CRM_Utils_Array::value('tip', $rows)) {
      $chartData['tip'] = $rows['tip'];
    }

    //legend
    $chartData['xname'] = CRM_Utils_Array::value('xname', $rows);
    $chartData['yname'] = CRM_Utils_Array::value('yname', $rows);

    // carry some chart params if pass.
    foreach (array(
      'xSize',
      'ySize',
      'divName'
    ) as $f) {
      if (CRM_Utils_Array::value($f, $rows)) {
        $chartData[$f] = $rows[$f];
      }
    }

    return self::buildChart($chartData, $chart);
  }
  static function reportChart($rows, $chart, $interval, &$chartInfo) {
    foreach ($interval as $key => $val) {
      $graph[$val] = $rows['value'][$key];
    }

    $chartData = array(
      'values' => $graph,
      'legend' => $chartInfo['legend'],
      'xname' => $chartInfo['xname'],
      'yname' => $chartInfo['yname']
    );

    // rotate the x labels.
    $chartData['xLabelAngle'] = CRM_Utils_Array::value('xLabelAngle', $chartInfo, 20);
    if (CRM_Utils_Array::value('tip', $chartInfo)) {
      $chartData['tip'] = $chartInfo['tip'];
    }

    // carry some chart params if pass.
    foreach (array(
      'xSize',
      'ySize',
      'divName'
    ) as $f) {
      if (CRM_Utils_Array::value($f, $rows)) {
        $chartData[$f] = $rows[$f];
      }
    }

    return self::buildChart($chartData, $chart);
  }
  function buildChart(&$params, $chart) {
    $openFlashChart = array();
    if ($chart && is_array($params) && ! empty($params)) {
      $chartInstance = new $chart($params);
      $chartInstance->buildChart();
      $chartObj = $chartInstance->getChart();
      dpm($chartObj);
      $openFlashChart = array();
      dpm($chartObj);
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

        $openFlashChart["chart_{$uniqueId}"]['size'] = array(
          'xSize' => $xSize,
          'ySize' => $ySize
        );
        $openFlashChart["chart_{$uniqueId}"]['object'] = $chartObj;

        // assign chart data to template
        $template = CRM_Core_Smarty::singleton();
        $template->assign('uniqueId', $uniqueId);
        //          $openFlashChart = '{ "elements": [ { "type": "bar_stack", "colours": [ "#C4D318", "#50284A", "#7D7B6A" ], "values": [ [ 2.5, 5, 2.5 ], [ 2.5, 5, 1.25, 1.25 ], [ 5, { "val": 5, "colour": "#ff0000" } ], [ 2, 2, 2, 2, { "val": 2, "colour": "#ff00ff" } ] ], "keys": [ { "colour": "#C4D318", "text": "Kiting", "font-size": 13 }, { "colour": "#50284A", "text": "Work", "font-size": 13 }, { "colour": "#7D7B6A", "text": "Drinking", "font-size": 13 }, { "colour": "#ff0000", "text": "XXX", "font-size": 13 }, { "colour": "#ff00ff", "text": "What rhymes with purple? Nurple?", "font-size": 13 } ], "tip": "X label [#x_label#], Value [#val#]
        //Total [#total#]" } ], "title": { "text": "Stuff I\'m thinking about, Mon Mar 11 2013", "style": "{font-size: 20px; color: #F24062; text-align: center;}" }, "x_axis": { "labels": { "labels": [ "Winter", "Spring", "Summer", "Autmn" ] } }, "y_axis": { "min": 0, "max": 14, "steps": 2 }, "tooltip": { "mouse": 2 } }';
        $template->assign("openFlashChartData", json_encode($openFlashChart));
      }
    }
    dpm($openFlashChart);
    return $openFlashChart;
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
  function __construct($params) {
    $chart = NULL;
    if (empty($params)) {
      return $chart;
    }
    $this->values = CRM_Utils_Array::value('values', $params);
    if (! is_array($this->values) || empty($this->values)) {
      return $chart;
    }
    $this->createChartElement();
    $this->chartElement->set_colour($this->_colours);
    // get the currency.
    $config = CRM_Core_Config::singleton();
    $symbol = $config->defaultCurrencySymbol;

    // set the tooltip.
    $this->tooltip = CRM_Utils_Array::value('tip', $params, "$symbol #val#");
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
class barchart extends chart {
  protected $xValues = array();
  protected $yValues = array();
  protected $xAxis = null;
  protected $yAxis = null;
  protected $yMin = 0;
  protected $yMax = 100;
  protected $ySteps = 5;
  protected $xAxisName = null;
  protected $yAxisName = null;
  protected $ylabelAngle = null;
  /**
 *
 * @param array $params
 */
  function __construct($params) {
    parent::__construct($params);
    foreach ($this->values as $xVal => $yVal) {
      $this->yValues[] = (double) $yVal;
      $this->xValues[] = (string) $xVal;
    }
    $this->xAxisName = CRM_Utils_Array::value('xname', $params);
    $this->yAxisName = CRM_Utils_Array::value('yname', $params);
    $this->xlabelAngle = CRM_Utils_Array::value('xlabelAngle', $params);
    $this->chartTitle = CRM_Utils_Array::value('legend', $params) ? $params['legend'] : ts('Bar Chart');

    //set values.
    $this->chartElement->set_values($this->yValues);
    $this->setYMaxYSteps($this->yValues);

    // call user define function to handle on click event.
    if ($onClickFunName = CRM_Utils_Array::value('on_click_fun_name', $params)) {
      $this->chartElement->set_on_click($onClickFunName);
    }
  }

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
    // create x axis label obj.
    $xLabels = new x_axis_labels();
    $xLabels->set_labels($this->xValues);

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
}

/**
   *
   * Stack bar chart class
   * @author eileen
   *
   */
class barChartStack extends barchart {
  function __construct($params) {
    parent::__construct($params);
    foreach ($this->values as $valueArray) {
      $this->chartElement->append_stack($valueArray);
        $totals[] = array_sum($valueArray);
      }
    $this->setYMaxYSteps($totals);
    // call user define function to handle on click event.
    if ($onClickFunName = CRM_Utils_Array::value('on_click_fun_name', $params)) {
      $bar_stack->set_on_click($onClickFunName);
    }
  }
  /**
     * Add main element
     */
  function createChartElement() {
    $this->chartElement = new bar_stack();
  }
}


