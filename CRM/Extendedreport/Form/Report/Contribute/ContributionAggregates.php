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
class CRM_Extendedreport_Form_Report_Contribute_ContributionAggregates extends CRM_Extendedreport_Form_Report_ExtendedReport {
  CONST OP_SINGLEDATE = 3;
  function buildChart(&$rows) {
    $graphRows = array();
    $count = 0;
    // build the chart.
    $config = CRM_Core_Config::Singleton();
    $graphRows['xname'] = '6 months';
    $graphRows['yname'] = "Amount ({$config->defaultCurrency})";
    CRM_Utils_OpenFlashChart::chart($rows, 'barChartStack', $this->_interval);
    $this->assign('chartType', $this->_params['charts']);
  }

  /**
   * Convert descriptor into a series of ranges. Note that the $extra array
   * may denote parameters or values (this allows us to easily flick between
   * allowing things like the offset_unit or no_periods to be hard-coded in the report or an
   * option
   *
   * @param array $extra
   */
  function multiplePeriodsConstructRanges($extra) {
    $vars = array(
      'cutoff_date',
      'no_periods',
      'offset_unit',
      'offset',
      'catchment_offset',
      'catchment_offset_unit'
    );
    foreach ($vars as $var) {
      if (! empty($this->_params[$extra[$var]])) {
        $$var = $this->_params[$extra[$var]];
      }
      else {
        $$var = $extra[$var];
      }
    }
    // start of our period is the cutoff date - the sum of all our periods + one day (as ranges expected to run 01 Jan to 31 Dec etc)
    $startDate = date('Y-m-d', strtotime("- " . ($no_periods * $offset) . " $offset_unit ", strtotime('+ 1 day', strtotime($cutoff_date))));
    $ranges = array();
    for($i = 0; $i < $no_periods; $i ++) {
      $rangeStart = date('Y-m-d', strtotime('+ ' . ($i * $offset) . " $offset_unit ", strtotime($startDate)));
      $rangeEnd = date('Y-m-d', strtotime(" +  $offset  $offset_unit", strtotime('- 1 day', strtotime($rangeStart))));
      $rangeCatchmentStart = date('Y-m-d', strtotime(' + 1 day', strtotime($rangeEnd)));
      $rangeCatchmentEnd = date('Y-m-d', strtotime(" + $catchment_offset $catchment_offset_unit", strtotime('- 1 day', strtotime($rangeCatchmentStart))));
      $ranges['interval_' . $i] = array(
        'from_date' => $rangeStart,
        'to_date' => $rangeEnd,
        'catchment_from_date' => $rangeCatchmentStart,
        'catchment_to_date' => $rangeCatchmentEnd
      );
    }
    return $ranges;
  }
  /*
    *      )
  *  but they are constructed in the construct fn -
  *  @todo we should move table construction to separate fn
  *   - OR an end date + an offset + a number of periods - in which case it
  *   will be constructed into the above
  *
  *   e.g
  *   array(
    *    'cutoff_date' => 'receive_date_to'
    *    'offset_unit' => 'year',
    *    'offset' => 1,
    *    'catchment_offset' => 18,
    *    'catchment_offset_unit' => 'month',
    *    'no_periods' => 4,
    *
    *
    */
  function joinContributionMulitplePeriods($prefix, $extra) {
    $ranges = $this->multiplePeriodsConstructRanges($extra);
    if (! $this->_preConstrained) {
      if (empty($this->_aliases['civicrm_contact'])) {
        $this->_aliases['civicrm_contact'] = 'civicontact';
      }
      //we are just going to add our where clause here
      $this->_params['receive_date_from'] = $ranges['interval_0']['from_date'];
      $this->_params['receive_date_to'] = $this->_params['receive_date_value'];
      return;
    }
    unset ($this->_params['receive_date_from']);
    unset ($this->_params['receive_date_to']);
    $this->_columns['civicrm_contribution']['filters']['receive_date']['pseudofield'] = TRUE;
    $tempTable = $this->constructComparisonTable($ranges);
    $this->_from = " FROM {$tempTable}_summary";
  }

  /**
  * Build array of contributions against contact
  *  Ideally this function works from the following params
  *
  *    'date_ranges' => array(
  *      'first_date_range' => array(
  *        'from_date' => '2009-01-01',
  *        'to_date' => '2010-06-06',
  *        'catchment_from_date' => '2008-01-01',
  *        'catchment_to_date' => '2009-01-01',
  *        ),
  *      'second_date_range => array(
  *        from_date' => '2011-01-01',
  *        'to_date' => '2011-06-06',
  *        'catchment_from_date' => '2010-01-01',
  *        'catchment_to_date' => '2010-06-01',),
  */
  function constructComparisonTable($ranges) {
    $columnStr = '';
    $betweenClauses = array();
    foreach ($ranges as $alias => &$specs) {

      $specs['between'] = "
      BETWEEN '{$specs['from_date']}'
      AND '{$specs['to_date']} 23:59:59'";
      $specs['catchment_between'] = "
        BETWEEN '{$specs['catchment_from_date']}'
          AND '{$specs['catchment_to_date']} 23:59:59'";
      $betweenClauses[] = " {$specs['between']}";
      $betweenClauses[] = " {$specs['catchment_between']}";

      $columnStr .= "  {$alias}_amount FLOAT NOT NULL default 0, {$alias}_no FLOAT NOT NULL default 0, ";
      $columnStr .= "  {$alias}_catch_amount FLOAT NOT NULL default 0, {$alias}_catch_no FLOAT NOT NULL default 0, ";
    }

    $temporary = $this->_temporary;
    $tempTable = 'civicrm_temp_conts' . rand(1, 10000);
    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS $tempTable");
    $createTablesql = "
                  CREATE  $temporary TABLE $tempTable (
                  `cid` INT(10) UNSIGNED NULL DEFAULT '0' COMMENT 'Contact ID',
                  `first_receive_date` DATE NOT NULL,
                  $columnStr
                  `total_amount` FLOAT NOT NULL,
                  INDEX `ContributionId` (`cid`)
                  )
                  COLLATE='utf8_unicode_ci'
                  ENGINE=HEAP;";

    $insertContributionRecordsSql = "
                  INSERT INTO $tempTable (cid, first_receive_date, total_amount)
                  SELECT {$this->_aliases[$this->_baseTable]}.id ,
                  min(receive_date), sum(total_amount)
                  FROM {$this->_baseTable} {$this->_aliases[$this->_baseTable]}
                  INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                  ON {$this->_aliases[$this->_baseTable]}.id =  {$this->_aliases['civicrm_contribution']}.contact_id
                  WHERE  {$this->_aliases['civicrm_contribution']}.contribution_status_id = 1
                  GROUP BY {$this->_aliases[$this->_baseTable]}.id
                  ";
    /*
                  * Note we are stashing total amount & count since it seems like it opens up other options. However, it's not strictly in the requirement
                  * so if we have performance issues with the subquery using IS NOT NULL may be quicker
                  * UPDATE civicrm_temp_conts7221 t,
                  SELECT * FROM( SELECT sum(total_amount) as total_amount, count(contribution_civireport.).id as no_cont
                    FROM civicrm_temp_conts7221
                    INNER JOIN civicrm_contribution contribution_civireport ON civicrm_temp_conts7221.cid = contribution_civireport.contact_id
                    SET second_date_range_amount = contribution_civireport.id IS NOT NULL
                    WHERE contribution_civireport.receive_date
                    BETWEEN '2010-01-01' AND '2011-06-06 23:59:59'
                    GROUP BY contact_id) as conts
                    */
    foreach ($ranges as $rangeName => &$rangespecs) {
      $inserts[] = " UPDATE $tempTable t,
                  (  SELECT contact_id, sum(cont.total_amount) as total_amount, count(cont.id) as no_cont
                  FROM $tempTable tmp
                  INNER JOIN civicrm_contribution cont ON tmp.cid = cont.contact_id
                  WHERE cont.receive_date
                  BETWEEN '{$rangespecs['from_date']}' AND '{$rangespecs['to_date']} 23:59:59'
                  GROUP BY contact_id
                  ) as conts
                  SET {$rangeName}_amount = conts.total_amount,
                  {$rangeName}_no = no_cont
                  WHERE t.cid = contact_id
                  ";

      $inserts[] = " UPDATE $tempTable t,
                  (  SELECT contact_id, sum(cont.total_amount) as total_amount, count(cont.id) as no_cont
                  FROM $tempTable tmp
                  INNER JOIN civicrm_contribution cont ON tmp.cid = cont.contact_id
                  WHERE cont.receive_date
                  BETWEEN '{$rangespecs['catchment_from_date']}' AND '{$rangespecs['catchment_to_date']} 23:59:59'
                  GROUP BY contact_id
                  ) as conts
                  SET {$rangeName}_catch_amount = conts.total_amount,
                  {$rangeName}_catch_no = no_cont
                  WHERE t.cid = contact_id
                  ";
    }

    CRM_Core_DAO::executeQuery($createTablesql);
    CRM_Core_DAO::executeQuery($insertContributionRecordsSql);
    foreach ($inserts as $sql) {
      CRM_Core_DAO::executeQuery($sql);
    }

    foreach ($ranges as $rangeName => &$rangespecs) {
      // could do this above but will probably want this creation in a separate function
      $summarySQL[] = "
        SELECT '{$rangespecs['from_date']}' as from_date,
        '{$rangespecs['to_date']}' as to_date,
        SUM(IF
        ({$rangeName}_amount > 0 AND {$rangeName}_catch_amount = 0 , 1,  0
        )) AS lapsed,
        SUM(IF
        ({$rangeName}_amount > 0 AND {$rangeName}_catch_amount > 0, 1,  0  )) AS renewals
        FROM {$tempTable}";
    }
    $newTableSQL = " CREATE table {$tempTable}_summary" . implode(' UNION ', $summarySQL);
    CRM_Core_DAO::executeQuery($newTableSQL);
    return $tempTable;
  }
}

