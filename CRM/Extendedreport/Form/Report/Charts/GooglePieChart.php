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
  protected $xlabels = null;
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
    $this->xlabelAngle = CRM_Utils_Array::value('xlabelAngle', $params, 30);
    $this->chartTitle = CRM_Utils_Array::value('legend', $params, ts('Bar Chart'));
    // call user define function to handle on click event.
    if ($this->onClickFunName) {
      $this->chartElement->set_on_click($this->onClickFunName);
    }
  }

}