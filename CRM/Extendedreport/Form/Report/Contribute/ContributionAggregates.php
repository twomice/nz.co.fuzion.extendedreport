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
 *
 * This is the base class for contribution aggregate reports. The report constructs a table
 * for a series of one or more data ranges and comparison ranges
 * The comparison range is the range to be compared against the main date range
 *
 * 4 types of comparitive data can be derived
 *  1) renewals - people who gave in both the base & the comparison period
 *  2) lapsed - people who gave in the comparison period only
 *  3) new - people who gave in the base period but not the comparison period
 *  4) reactivations - people who gave in the new period but not the comparison period
 *    but also gave on an earlier occasion during the report universe
 *
 *  Where are reportingStartDate is set the report 'universe' is only  those contributions after that date
 *
 *  The report builds up the pairs of ranges (base & comparison) for 3 main scenarios
 *    1) comparison is a future range, in this case the comparison period generally starts the day after the
 *    main period. This is used for the renewals where we want to look at one period & see what happened to the
 *    donors from that period in the next period - did they lapse or renew
 *
 *    2) comparison is 'allprior' - ie. any contributions in the report universe prior to the base date
 *    are treated as comparison. This used for the Recovery report where we see if people who gave
 *    prior to the base period gave (reactivated) or didn't give (lapsed) in the base period
 *
 *    3) comparison is prior - in this case the comparison is a prior range but does not go back as far as
 *    the report universe unless it co-incides with it.
 *
 */
class CRM_Extendedreport_Form_Report_Contribute_ContributionAggregates extends CRM_Extendedreport_Form_Report_ExtendedReport {
  CONST OP_SINGLEDATE = 3;
  protected $_add2groupSupported = FALSE;
  protected $_ranges = array();
  protected $_reportingStartDate = NULL;
  protected $_comparisonType = 'future'; // is the comparison period future, a priorrange, or all prior (after the reporting range starts)
  protected $_barChartLegend = NULL;
  protected $_baseEntity = NULL;
  /**
   * These are the labels for the available statuses.
   * Reports can over-ride them
   */
  protected $_statusLabels = array(
    'renewed' => 'Renewed',
    'lapsed' => 'Lapsed',
    'recovered' => 'Recovered',
    'new' => 'New',
    );
  /**
   *
   * @var array statuses to include in report
   */
  protected $_statuses = array();


  function buildChart(&$rows) {
    $graphData = array();
    foreach ($this->_statuses as $status){
      $graphData['labels'][]  = $this->_statusLabels[$status];
    }
    if($this->_params['charts'] == 'multiplePieChart'){
      return $this->mulitplePieChart($rows, $graphData);
    }

    foreach ($rows as $index => $row) {
      $graphData['xlabels'][] = $this->_params['contribution_baseline_interval_value'] . ts(" months to ") . $row['to_date'];
      $graphData['end_date'][] = $row['to_date'];
      $statusValues = array();
      foreach ($this->_statuses as $status){
        $statusValues[] = (integer) $row[$status];
      }
      $graphData['values'][] = $statusValues;
    }

    // build the chart.
    $config = CRM_Core_Config::Singleton();
    $graphData['xname'] = ts('Base contribution period');
    $graphData['yname'] = ts("Number of Donors");

    $graphData['legend'] = ts($this->_barChartLegend);
    CRM_Extendedreport_Form_Report_OpenFlashChart::buildChart($graphData, 'barChartStack');
    $this->assign('chartType', $this->_params['charts']);
  }

  function mulitplePieChart(&$rows, $graphData){
    foreach ($rows as $index => $row) {
      $graphData['xlabels'][] = $this->_params['contribution_baseline_interval_value'] . ts(" months to ") . $row['to_date'];
      $graphData['end_date'][] = $row['to_date'];
      foreach ($this->_statuses as $status){
        $graphData['value'][] =
          (integer) $row[$status]
        ;
        $graphData['values'][$index][$status] = (integer) $row[$status];
      }
    }

    // build the chart.

     $graphData['xname'] = 'x';
     $config = CRM_Core_Config::Singleton();
     $graphData['yname'] = "Amount ({$config->defaultCurrency})";
     $chartInfo = array('legend' => $this->_barChartLegend);
     $chartInfo['xname'] = ts('Base contribution period');
     $chartInfo['yname'] = ts("Number of Donors");
     $chartData = CRM_Utils_OpenFlashChart::reportChart( $graphData, 'pieChart', $this->_statuses, $chartInfo);
     $this->assign('chartType', 'pieChart');
     $this->assign('chartsData', $graphData['values']);
     $this->assign('chartsLabels', array('status', 'no. contacts'));
     $this->assign('chartInfo', $chartInfo);
  }


  function alterDisplay(&$rows){
    $potentialCriteria = array(
      'financial_type_id_value',
      'financial_type_id_op',
      'payment_instrument_id_op',
      'payment_instrument_id_value',
      'contribution_status_id_value',
      'contribution_status_id_op',
      'contribution_is_test_op',
      'contribution_is_test_value',
      'total_amount_min',
      'total_amount_max',
      'total_amount_op',
      'total_amount_value',
      'tagid_op',
      'tagid_value',
      'gid_op',
      'gid_value'
      );
    $queryURL = "reset=1&force=1";
    foreach ($potentialCriteria as $criterion){
      if(empty($this->_params[$criterion])){
        continue;
      }
      $criterionValue = is_array($this->_params[$criterion]) ? implode(',', $this->_params[$criterion]) : $this->_params[$criterion];
      $queryURL .= "&{$criterion}=" . $criterionValue;
    }
    foreach ($rows as $index => &$row){
      foreach ($this->_statuses as $status){
        if(array_key_exists($status, $row)){
          $statusUrl = CRM_Report_Utils_Report::getNextUrl(
          'contribute/aggregatedetails',
          $queryURL
          . "&receive_date_from=" . date('Ymd', strtotime($row['from_date']))
          . "&receive_date_to=" . date('Ymd', strtotime($row['to_date']))
          . "&comparison_date_from=". date('Ymd', strtotime($this->_ranges['interval_' . $index]['comparison_from_date']))
          . "&comparison_date_to=". date('Ymd', strtotime($this->_ranges['interval_' . $index]['comparison_to_date']))
          . "&behaviour_type_value={$status}",
          $this->_absoluteUrl,
          NULL,
          $this->_drilldownReport
          );
          $row[$status . '_link'] = $statusUrl;
        }
      }
    }
    parent::alterDisplay($rows);
  }

  /**
   * Convert descriptor into a series of ranges. Note that the $extra array
   * may denote parameters or values (this allows us to easily flick between
   * allowing things like the offset_unit or no_periods to be hard-coded in the report or an
   * option
   *
   * @param array $extra
   */
  function constructRanges($extra) {
    $vars = array(
      'cutoff_date',
      'no_periods',
      'offset_unit',
      'offset',
      'comparison_offset',
      'comparison_offset_unit',
      'start_offset',
      'start_offset_unit',
    );
    foreach ($vars as $var) {
      if (isset($extra[$var]) && !empty($this->_params[$extra[$var]])) {
        $$var = $this->_params[$extra[$var]];
      }
      else {
        $$var = empty($extra[$var]) ? NULL : $extra[$var];
      }
    }
    // start of our period is the cutoff date - the sum of all our periods + one day (as ranges expected to run 01 Jan to 31 Dec etc)
    $startDate = date('Y-m-d', strtotime("- " . ($no_periods * $offset) . " $offset_unit ", strtotime('+ 1 day', strtotime($cutoff_date))));
    $this->_ranges = array();
    for($i = 0; $i < $no_periods; $i ++) {
      if($this->_comparisonType  == 'future'){
        $this->constructFutureRanges($i, $startDate, $no_periods, $offset_unit, $offset,  $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit);
      }
      if($this->_comparisonType == 'allprior' || $this->_comparisonType == 'prior'){
        $this->constructPriorRanges($i, $startDate, $no_periods, $offset_unit, $offset,  $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit);
      }
    }
    return $this->_ranges;
  }
/**
 *
 * @param integer $i
 * @param string $startDate
 * @param integer $no_periods
 * @param string $offset_unit
 * @param integer $offset
 * @param string $comparison_offset
 * @param integer $comparison_offset_unit
 * @param string $start_offset
 * @param integer $start_offset_unit
 */
  function constructFutureRanges($i, $startDate, $no_periods, $offset_unit, $offset,  $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit){
    $rangestart = date('Y-m-d', strtotime('+ ' . ($i * $offset) . " $offset_unit ", strtotime($startDate)));
    $rangeEnd = date('Y-m-d', strtotime(" +  $offset  $offset_unit", strtotime('- 1 day', strtotime($rangestart))));
    $rangeComparisonStart = date('Y-m-d', strtotime(' + 1 day', strtotime($rangeEnd)));
    $rangeComparisonEnd = date('Y-m-d', strtotime(" + $comparison_offset $comparison_offset_unit", strtotime('- 1 day', strtotime($rangeComparisonStart))));
    $this->_ranges['interval_' . $i] = array(
      'from_date' => $rangestart,
      'to_date' => $rangeEnd,
      'comparison_from_date' => $rangeComparisonStart,
      'comparison_to_date' => $rangeComparisonEnd
    );
  }

  /**
   *
   * @param integer $i
   * @param string $startDate
   * @param integer $no_periods
   * @param string $offset_unit
   * @param integer $offset
   * @param string $comparison_offset
   * @param integer $comparison_offset_unit
   */
  function constructPriorRanges($i, $startDate, $no_periods, $offset_unit, $offset,  $comparison_offset, $comparison_offset_unit, $start_offset, $start_offset_unit){
    $rangestart = date('Y-m-d', strtotime('+ ' . ($i * $offset) . " $offset_unit ", strtotime($startDate)));
    $rangeEnd = date('Y-m-d', strtotime(" +  $offset  $offset_unit", strtotime('- 1 day', strtotime($rangestart))));
    $rangeComparisonEnd = date('Y-m-d',  strtotime('- 1 day', strtotime($rangestart)));
    $rangeComparisonStart = date('Y-m-d', strtotime(" - $comparison_offset $comparison_offset_unit", strtotime('+ 1 day', strtotime($rangeComparisonEnd))));
    if($this->_reportingStartDate && $this->_comparisonType == 'allprior'){
      $rangeComparisonStart = $this->_reportingStartDate;
    }
    $this->_ranges['interval_' . $i] = array(
      'from_date' => $rangestart,
      'to_date' => $rangeEnd,
      'comparison_from_date' => $rangeComparisonStart,
      'comparison_to_date' => $rangeComparisonEnd
    );
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
    *    'comparison_offset' => 18,
    *    'comparison_offset_unit' => 'month',
    *    'no_periods' => 4,
    *    'start_offset' = > 60
    *    'start_offset_unit' => 'month'
    *
    *
    */
  function joinContributionMulitplePeriods($prefix, $extra) {
    if (! $this->_preConstrained) {
      if (empty($this->_aliases['civicrm_contact'])) {
        $this->_aliases['civicrm_contact'] = 'civicontact';
      }

      //we are just going to add our where clause here
      $this->_params['receive_date_to'] = $this->_params['receive_date_value'];
      if (! empty($extra['start_offset'])) {
        $this->_params['receive_date_from'] = $this->_reportingStartDate;
      }
      else {
        $this->_params['receive_date_from'] = $this->_ranges['interval_0']['from_date'];
      }
      return;
    }
    unset($this->_params['receive_date_from']);
    unset($this->_params['receive_date_to']);
    $this->_columns['civicrm_contribution']['filters']['receive_date']['pseudofield'] = TRUE;
    $tempTable = $this->constructComparisonTable();

    //@todo hack differentiating summary based on contact & contribution report
    // do something better
    if($this->_baseEntity == 'contribution'){
      if(empty($this->aliases['civicrm_contribution'])){
        $this->aliases['civicrm_contribution'] = 'contribution_civireport';
      }
      $baseFrom = " {$this->_baseTable} " . (empty($this->_aliases[$this->_baseTable]) ? '': $this->_aliases[$this->_baseTable]);
      $this->_from = str_replace('FROM' . $baseFrom ,
        "FROM  $tempTable tmptable INNER JOIN civicrm_contribution
       {$this->aliases['civicrm_contribution']} ON tmptable.cid = {$this->aliases['civicrm_contribution']}.contact_id
       AND tmptable.interval_0_{$this->_params['behaviour_type_value']} = 1
       AND {$this->aliases['civicrm_contribution']}.is_test=0
       INNER JOIN $baseFrom ON {$this->_aliases[$this->_baseTable]}.id = {$this->_aliases['civicrm_contribution']}.contact_id
       ", $this->_from);
    }
    else{
      $this->createSummaryTable($tempTable);
      $this->_from = " FROM {$tempTable}_summary";
    }
    $this->whereClauses = array();
  }
/**
 * Set the report date range where the report dates are defined by an end date and
 * an offset
 * @param array $startParams
 *  - start_offset
 *  - start_offset_unit
 */
  function setReportingStartDate($startParams){
    if (!empty($startParams['start_offset']) && !$this->_reportingStartDate) {
      $startOffset = CRM_Utils_Array::value($startParams['start_offset'], $this->_params, $startParams['start_offset']);
      $startOffsetUnit = CRM_Utils_Array::value($startParams['start_offset_unit'], $this->_params, $startParams['start_offset_unit']);
      $this->_reportingStartDate = date('Y-m-d', strtotime("-  $startOffset  $startOffsetUnit ", strtotime($this->_params['receive_date_value'])));
    }
  }

  function constrainedWhere(){
    if(empty($this->constrainedWhereClauses)){
      $this->_where = "WHERE ( 1 ) ";
      $this->_having = "";
    }
    else {
      $this->_where = "WHERE " . implode(' AND ', $this->constrainedWhereClauses);
    }

  }
  /*
  * Here we have one period & a comparison
  * Receive date from / to are compulsory for this
  * as are comparison_dates & type
  *
  */
  function joinContributionSinglePeriod($prefix, $extra) {
    //@todo this setting of aliases is just a hack
    if (empty($this->_aliases['civicrm_contact'])) {
      $this->_aliases['civicrm_contact'] = 'civicontact';
    }
    if (empty($this->_aliases['civicrm_contribution'])) {
      $this->aliases['civicrm_contribution'] = 'contribution_civireport';
    }
    if (! $this->_preConstrained) {
      return;
    }
    //@todo - not sure if we need this separate from 'mulitple' - main difference is handling around 'receive_date
    // because in single we are using the receive date

    $tempTable = $this->constructComparisonTable();
    //@todo hack differentiating summary based on contact & contribution report
    // do something better
    if($this->_baseEntity == 'contribution'){

      $baseFrom = " {$this->_baseTable} " . (empty($this->_aliases[$this->_baseTable]) ? '': $this->_aliases[$this->_baseTable]);
      $this->_from = str_replace('FROM' . $baseFrom , "
        FROM  {$this->_baseTable} tmpcontacts
        INNER JOIN  $tempTable tmpConttable ON tmpcontacts.id = tmpConttable.cid
        INNER JOIN civicrm_contact {$this->_aliases[$this->_baseTable]} ON {$this->_aliases[$this->_baseTable]}.id = tmpcontacts.id
        LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
          ON tmpConttable.cid = {$this->_aliases['civicrm_contribution']}.contact_id
          AND {$this->_aliases['civicrm_contribution']}.receive_date
            BETWEEN '{$this->_ranges['interval_0']['from_date']}' AND
            '{$this->_ranges['interval_0']['to_date']}'
        ", $this->_from);
      $this->constrainedWhereClauses = array("tmpConttable.interval_0_{$this->_params['behaviour_type_value']} = 1");
    }
    else{
      $this->createSummaryTable($tempTable, $extra['statuses']);
      $this->_from = " FROM {$tempTable}_summary";
    }
  }


  /**
  * Build array of contributions against contact
  *  Ideally this function works from the following params
  *
  *    'date_ranges' => array(
  *      'first_date_range' => array(
  *        'from_date' => '2009-01-01',
  *        'to_date' => '2010-06-06',
  *        'comparison_from_date' => '2008-01-01',
  *        'comparison_to_date' => '2009-01-01',
  *        ),
  *      'second_date_range => array(
  *        from_date' => '2011-01-01',
  *        'to_date' => '2011-06-06',
  *        'comparison_from_date' => '2010-01-01',
  *        'comparison_to_date' => '2010-06-01',),
  */
  function constructComparisonTable() {
    $columnStr = '';
    $betweenClauses = array();
    foreach ($this->_ranges as $alias => &$specs) {

      $specs['between'] = "
      BETWEEN '{$specs['from_date']}'
      AND '{$specs['to_date']} 23:59:59'";
      $specs['comparison_between'] = "
        BETWEEN '{$specs['comparison_from_date']}'
          AND '{$specs['comparison_to_date']} 23:59:59'";
      $betweenClauses[] = " {$specs['between']}";
      $betweenClauses[] = " {$specs['comparison_between']}";

      $columnStr .= "  {$alias}_amount FLOAT NOT NULL default 0, {$alias}_no FLOAT NOT NULL default 0, ";
      $columnStr .= "  {$alias}_catch_amount FLOAT NOT NULL default 0, {$alias}_catch_no FLOAT NOT NULL default 0, ";
      foreach ($this->_statuses as $status){
        $columnStr .= "  {$alias}_{$status} TINYINT NOT NULL default 0, ";
      }
    }

    $temporary = $this->_temporary;
    $tempTable = 'civicrm_report_temp_conts' . date('d_H_I') . rand(1, 10000);
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
    $contributionClause = $receiveClause = '';
    if (! empty($this->whereClauses['civicrm_contribution'])) {
      foreach ($this->whereClauses['civicrm_contribution'] as $clause){
        if(stristr($clause, 'receive_date')){
          $receiveClause = " AND " . $clause;
        }
        else{
          $contributionClause = " AND " . $clause;
        }
      }
    }

    $insertContributionRecordsSql = "
                  INSERT INTO $tempTable (cid, first_receive_date, total_amount)
                  SELECT {$this->_aliases[$this->_baseTable]}.id ,
                  min(receive_date), sum(total_amount)
                  FROM {$this->_baseTable} {$this->_aliases[$this->_baseTable]}
                  INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}
                  ON {$this->_aliases[$this->_baseTable]}.id =  {$this->_aliases['civicrm_contribution']}.contact_id
                  WHERE  {$this->_aliases['civicrm_contribution']}.contribution_status_id = 1
                  AND {$this->_aliases['civicrm_contribution']}.is_test = 0
                  $receiveClause
                  $contributionClause
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
    foreach ($this->_ranges as $rangeName => &$rangeSpecs) {
      $inserts[] = " UPDATE $tempTable t,
                  (  SELECT contact_id, sum({$this->_aliases['civicrm_contribution']}.total_amount) as total_amount,
                      count({$this->_aliases['civicrm_contribution']}.id) as no_cont
                    FROM $tempTable tmp
                    INNER JOIN civicrm_contribution  {$this->_aliases['civicrm_contribution']} ON tmp.cid = {$this->_aliases['civicrm_contribution']}.contact_id
                    WHERE {$this->_aliases['civicrm_contribution']}.receive_date
                    BETWEEN '{$rangeSpecs['from_date']}' AND '{$rangeSpecs['to_date']} 23:59:59'
                    $contributionClause
                    GROUP BY contact_id
                  ) as conts
                  SET {$rangeName}_amount = conts.total_amount,
                  {$rangeName}_no = no_cont
                  WHERE t.cid = contact_id
                  ";

      $inserts[] = " UPDATE $tempTable t,
                  (  SELECT contact_id,
                      sum({$this->_aliases['civicrm_contribution']}.total_amount) as total_amount,
                      count({$this->_aliases['civicrm_contribution']}.id) as no_cont
                    FROM $tempTable tmp
                    INNER JOIN civicrm_contribution  {$this->_aliases['civicrm_contribution']} ON tmp.cid = {$this->_aliases['civicrm_contribution']}.contact_id
                    WHERE {$this->_aliases['civicrm_contribution']}.receive_date
                    BETWEEN '{$rangeSpecs['comparison_from_date']}' AND '{$rangeSpecs['comparison_to_date']} 23:59:59'
                    $contributionClause
                    GROUP BY contact_id
                  ) as conts
                  SET {$rangeName}_catch_amount = conts.total_amount,
                  {$rangeName}_catch_no = no_cont
                  WHERE t.cid = contact_id
                  ";
      foreach ($this->_statuses as $status){
        $statusClauses[] = "
           {$rangeName}_{$status} = " . $this->getStatusClause($status, $rangeName, $rangeSpecs);
      }
    }
    if(!empty($statusClauses)){
      $inserts[] = " UPDATE $tempTable t SET " . implode(',', $statusClauses);
    }
    CRM_Core_DAO::executeQuery($createTablesql);
    CRM_Core_DAO::executeQuery($insertContributionRecordsSql);
    foreach ($inserts as $sql) {
      CRM_Core_DAO::executeQuery($sql);
    }
    return $tempTable;
  }
  /**
 *
 * @param string $tempTable
 * @param array $this->_ranges
 * @return string
 */
  function createSummaryTable($tempTable) {
    foreach ($this->_ranges as $rangeName => &$rangeSpecs) {
      // could do this above but will probably want this creation in a separate function
      $sql = "
      SELECT
      '$rangeName' as range_name,
      '{$rangeSpecs['from_date']}' as from_date,
      '{$rangeSpecs['to_date']}' as to_date ";
      foreach ($this->_statuses as $status){
        $sql .= " , SUM(
          {$rangeName}_{$status}
        ) AS {$status} ";
      }

      $summarySQL[] = $sql . " FROM {$tempTable}";
    }

    $newTableSQL = " CREATE table {$tempTable}_summary" . implode(' UNION ', $summarySQL);
    CRM_Core_DAO::executeQuery($newTableSQL);
  }
/**
 * Wrapper for status clauses
 * @param string $status
 * @param string $rangeName
 */
 function getStatusClause($status, $rangeName, $rangeSpecs){
   $fn = 'get' . ucfirst($status) . 'Clause';
   return $this->$fn($rangeName, $rangeSpecs);
 }

 /**
 * Get Clause for lapsed
 */
  function getLapsedClause($rangeName, $rangeSpecs) {
    return "
        IF (
          {$rangeName}_amount = 0 AND {$rangeName}_catch_amount > 0, 1,  0
         )
    ";
  }
  /**
   * Get Clause for Recovered
   */
  function getRecoveredClause($rangeName, $rangeSpecs) {
    return "
        IF (
         {$rangeName}_amount > 0 AND (
           {$rangeName}_catch_amount = 0 AND first_receive_date < '{$rangeSpecs['from_date']}'
         ) , 1,  0
        )
     ";
  }

  /**
   * Get Clause for Renewed
   * These are where the contribution happened in both periods
   * - note that the term 'renewal' & the term Recovered are easily confused
   * but recovered is used where the comparison period is 'prior' but not 'priorall'
   * so there is a period not covered in the comparison period but covered in the
   * report 'universe'
   */
  function getRenewedClause($rangeName, $rangeFromDate) {
    return "
      IF (
        {$rangeName}_amount > 0 AND {$rangeName}_catch_amount > 0, 1,  0
      )
    ";
  }

  function getNewClause($rangeName, $rangeFromDate){
    return "
    IF (
    {$rangeName}_amount > 0 AND {$rangeName}_catch_amount = 0, 1,  0
    )
    ";
  }
  /**
   * (non-PHPdoc)
   * @see CRM_Extendedreport_Form_Report_ExtendedReport::getAvailableJoins()
   */
  function getAvailableJoins() {
    return parent::getAvailableJoins() + array(
      'timebased_contribution_from_contact' => array(
        'callback' => 'joinContributionMulitplePeriods'
      ),
      'single_contribution_comparison_from_contact' => array(
        'callback' => 'joinContributionSinglePeriod'
      ),
    );
  }
  /**
   * We have some overloaded vars which could either be a constant of a param - convert
   * @param unknown_type $vars
   */
  function getVarsFromParams(&$vars) {
  }
}

