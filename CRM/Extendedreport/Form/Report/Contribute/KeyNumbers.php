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
class CRM_Extendedreport_Form_Report_Contribute_KeyNumbers extends CRM_Extendedreport_Form_Report_Contribute_ContributionAggregates {
  protected $_temporary = '  ';
  protected $_baseTable = 'civicrm_contact';
  protected $_noFields = TRUE;
  protected $_kpis = array();
  protected $_preConstrain = TRUE;

  protected $_kpiDescriptors = array(
      'no_people' => 'Total Number of Donors',
      'total_amount' => 'Amount Raised',
      'total_amount_individual' => 'Amount of Donations By Individuals',
      'number_individual' => 'Number of Individual Donations',
      'average_donation' => 'Average Donation',
      'no_increased_donations' => 'Individual Donors who Increased their donation',
 //     'no_sustainers' => 'Number of sustainers',
 //     'total_contacts' => 'New Contacts in the database',
  );

  protected $_financialKpiS = array(
      'total_amount',
      'total_amount_individual',
      'average_donation' => 'Average Donation',
    );
  /*
   * we'll store these as a property so we only have to calculate once
   */
  protected $_currentYear = NULL;
  protected $_lastYear = NULL;
  protected $_yearBeforeLast = NULL;
  protected $_contributionWhere = '';

  protected $_charts = array(
  );

  protected $_statuses = array('increased');

  public $_drilldownReport = array('contribute/detail' => 'Link to Detail Report');

  function __construct() {
     $this->_currentYear = date('Y');
     $this->_lastYear = $this->_currentYear - 1;
     $this->_yearBeforeLast = $this->_currentYear - 2;
     $this->_columns =   $this->getContributionColumns(array(
       'fields' => FALSE,
       'order_by' => FALSE,
     ));
     unset($this->_columns['civicrm_contribution']['filters'] ['receive_date']);
     $this->_aliases['civicrm_contact']  = 'civicrm_report_contact';
     $this->_tagFilter = TRUE;
     $this->_groupFilter = TRUE;
     parent::__construct();
  }

  function preProcess() {
    parent::preProcess();
  }
  /**
   * (non-PHPdoc)
   * @see CRM_Extendedreport_Form_Report_ExtendedReport::getAvailableJoins()
   */
  function getAvailableJoins() {
    return parent::getAvailableJoins() + array(
      'compile_key_stats' => array(
        'callback' => 'compileKeyStats'
      ),
    );
  }

  function from(){
    parent::from();
  }

  /**
   * (non-PHPdoc)
   * @see CRM_Extendedreport_Form_Report_ExtendedReport::beginPostProcess()
   */
  function beginPostProcess() {
    parent::beginPostProcess();
    $this->_reportingStartDate = date('Y-m-d', strtotime('last day of December this year'));
    $this->constructRanges(array(
      'cutoff_date' => date('Y-m-d', strtotime('last day of December this year')),
      'start_offset' => '1',
      'start_offset_unit' => 'year',
      'offset_unit' => 'year',
      'offset' => '1',
      'comparison_offset' => '1',
      'comparison_offset_unit' => 'year',
      'comparison_offset_type' => 'prior', ///
      'no_periods' => 2,
      'statuses' => array('increased'),
    )
    );
  }
  function compileKeyStats(){
    $tempTable = $this->generateSummaryTable();
    $this->calcDonorNumber();
    $this->calcDonationTotal();
    $this->calcIndividualDonationTotal();
    $this->calcIndividualDonationNumber();
    $this->calcIncreasedGivers();
    $this->_from = " FROM $tempTable";
    $this->stashValuesInTable($tempTable);
  }
/*  function where(){
    $this->_where = "WHERE YEAR(receive_date) > (YEAR(CURRENT_DATE) - 2)
    AND is_test = 0 AND contribution_status_id = 1
    AND {$this->_aliases[$this->_baseTable]}.is_deleted = 0
    ";
  }*/
  function fromClauses( ) {
    if($this->_preConstrained){
      return $this->constrainedFromClause();
    }
    else{
      return array(
        'contribution_from_contact',
        'entitytag_from_contact',
      );
    }
  }
  /**
   * We need to calculate increases using the parent
   * @return
   */
  function constrainedFromClause(){
    return array(
      'timebased_contribution_from_contact' => array(
        array(
          'statuses' => array('increased'),
        )
      ),
      'compile_key_stats' => array(array()),
    );
  }

  function select(){
    if(!$this->_preConstrained){
      parent::select();
    }
    else{
      $columns = array(
        'description' => ts(''),
        'this_year' => ts('This Year'),
        'percent_change' => ts('Percent Change'),
        'last_year' => ts('Last Year'),
      );
      foreach ($columns as $column => $title){
        $select[]= " $column ";
        $this->_columnHeaders[$column] = array('title' => $title);
      }
      $this->_select = " SELECT " . implode(', ', $select);
    }
  }

/**
 * Generate empty temp table
 * (non-PHPdoc)
 * @see CRM_Extendedreport_Form_Report_ExtendedReport::generateTempTable()
 */
  function generateSummaryTable(){
    $tempTable = 'civicrm_report_temp_kpi' . date('d_H_I') . rand(1, 10000);
    $sql = " CREATE {$this->_temporary} TABLE $tempTable (
      description  VARCHAR(50) NULL,
      this_year INT(10) NULL,
      last_year INT(10) NULL,
      percent_change INT(10) NULL
    )";
    CRM_Core_DAO::executeQuery($sql);
    return $tempTable;
  }

 /**
  * Add data about number of donors
  */
  function calcDonorNumber(){
    $sql = "
      SELECT COALESCE(count(*),0) as no_people
      , EXTRACT(YEAR FROM (receive_date)) as report_year
      FROM
      (
        SELECT receive_date, contact_id FROM
         civicrm_contribution cont
         INNER JOIN {$this->_baseTable} {$this->_aliases[$this->_baseTable]}
           ON {$this->_aliases[$this->_baseTable]}.id = cont.contact_id
         {$this->_contributionWhere}
         GROUP BY contact_id
      ) as x
      GROUP BY EXTRACT(YEAR FROM (receive_date));
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $this->_kpis[$result->report_year]['no_people'] = $result->no_people;
    }
  }

  /**
   * Add data about number of donors
   */
  function calcDonationTotal(){
    $sql = "
      SELECT COALESCE(sum(total_amount),0) as total_amount
      , EXTRACT(YEAR FROM (receive_date)) as report_year
      FROM civicrm_contribution cont
      INNER JOIN {$this->_baseTable} {$this->_aliases[$this->_baseTable]}
        ON {$this->_aliases[$this->_baseTable]}.id = cont.contact_id
      {$this->_contributionWhere}
      GROUP BY EXTRACT(YEAR FROM (receive_date));
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $this->_kpis[$result->report_year]['total_amount'] = $result->total_amount;
    }
  }

  /**
   * Add data about number of donors
   */
  function calcIndividualDonationTotal(){
    $sql = "
    SELECT COALESCE(sum(total_amount),0) as total_amount_individual
    , EXTRACT(YEAR FROM (receive_date)) as report_year
    FROM civicrm_contribution cont
    INNER JOIN {$this->_baseTable} {$this->_aliases[$this->_baseTable]}
      ON {$this->_aliases[$this->_baseTable]}.id = cont.contact_id
    INNER JOIN civicrm_contact c ON c.id = {$this->_aliases[$this->_baseTable]}.id
    {$this->_contributionWhere}
    AND c.contact_type = 'Individual'
    GROUP BY EXTRACT(YEAR FROM (receive_date));
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $this->_kpis[$result->report_year]['total_amount_individual'] = $result->total_amount_individual;
    }
  }
  /**
   * Add data about number of donors
   */
  function calcIndividualDonationNumber(){
    $sql = "
    SELECT count(*) as number_individual
    , EXTRACT(YEAR FROM (receive_date)) as report_year
    FROM civicrm_contribution cont
    INNER JOIN {$this->_baseTable} {$this->_aliases[$this->_baseTable]}
      ON {$this->_aliases[$this->_baseTable]}.id = cont.contact_id
    {$this->_contributionWhere}
    GROUP BY EXTRACT(YEAR FROM (receive_date));
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $this->_kpis[$result->report_year]['number_individual'] = $result->number_individual;
      $this->_kpis[$result->report_year]['average_donation'] = $this->_kpis[$result->report_year]['total_amount'] / $result->number_individual;
    }
  }

  /**
   * Add data about number of donors
   */
  function calcIncreasedGivers(){
    $sql = "
    SELECT increased, to_date
    {$this->_from}
    ;
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $year = date('Y', strtotime($result->to_date));
      $this->_kpis[$year]['no_increased_donations'] = $result->increased;
    }
  }
/**
 * We are just stashing our array of values into a table here - we could potentially render without a table
 * but this seems simple.
 */
  function stashValuesInTable($temptable){
    foreach ($this->_kpiDescriptors as $key => $description){
      $lastYearValue = empty($this->_kpis[$this->_lastYear][$key]) ? 0 : $this->_kpis[$this->_lastYear][$key];
      $thisYearValue = empty($this->_kpis[$this->_currentYear][$key]) ? 0 : $this->_kpis[$this->_currentYear][$key];
      if($lastYearValue && $thisYearValue){
        $percent = ($this->_kpis[$this->_currentYear][$key]-  $this->_kpis[$this->_lastYear][$key])/ $this->_kpis[$this->_lastYear][$key] * 100;
      }
      else{
        $percent = 0;
      }
      $insert[] = "
        ('{$description}'
        , $thisYearValue
        , $lastYearValue
        , $percent
        )";
    }
    $insertClause = implode(',', $insert);
    $sql = "
        INSERT INTO $temptable VALUES $insertClause
      ";
    CRM_Core_DAO::executeQuery($sql);
  }
  /**
   * (non-PHPdoc)
   * @see CRM_Extendedreport_Form_Report_Contribute_ContributionAggregates::alterDisplay()
   */
  function alterDisplay(&$rows){
    foreach ($rows as &$row){
      $dollarFields = array('Amount Raised', 'Amount of Individual Donations');
      if(array_search($row['description'], $dollarFields)){
        $row['this_year'] = '$' . $row['this_year'];
        $row['last_year'] = '$' . $row['last_year'];
      }
      if($row['description'])
      if($row['percent_change'] == 0){
        $row['percent_change'] = 'n/a';
      }
      else{
        $row['percent_change'] = $row['percent_change'] . '%';
      }
      if($row['description'] =='Individual Donors who Increased their donation'){
        // this is copied & pasted from parent as unclear how to deal with the fact this is just a part
        // of a row this time - not a column
        $queryURL = "reset=1&force=1";
        foreach ($this->_potentialCriteria as $criterion){
          if(empty($this->_params[$criterion])){
            continue;
          }
          $criterionValue = is_array($this->_params[$criterion]) ? implode(',', $this->_params[$criterion]) : $this->_params[$criterion];
          $queryURL .= "&{$criterion}=" . $criterionValue;
        }
        $queryURLlastYear ="&comparison_date_from=". date("{$this->_yearBeforeLast}0101")
        . "&comparison_date_to=". date("{$this->_yearBeforeLast}1231")
        . "&receive_date_from=" . date("{$this->_lastYear}0101")
        . "&receive_date_to=" . date("{$this->_lastYear}1231");
        ;
        $lastYearUrl = CRM_Report_Utils_Report::getNextUrl(
          'contribute/aggregatedetails',
          $queryURL
          . "&behaviour_type_value=increased"
          . $queryURLlastYear,
          $this->_absoluteUrl,
          NULL,
          $this->_drilldownReport
        );
        $row['last_year_link'] = $lastYearUrl;
        $queryURLThisYear ="&comparison_date_from=". date("{$this->_lastYear}0101")
        . "&comparison_date_to=". date("{$this->_lastYear}1231")
        . "&receive_date_from=" . date("{$this->_currentYear}0101")
        . "&receive_date_to=" . date("{$this->_currentYear}1231");
        ;
        $statusUrl = CRM_Report_Utils_Report::getNextUrl(
          'contribute/aggregatedetails',
           $queryURL
           . "&behaviour_type_value=increased"
           . $queryURLThisYear,
            $this->_absoluteUrl,
            NULL,
            $this->_drilldownReport
            );
            $row['this_year_link'] = $statusUrl;
        }
      }
    parent::alterDisplay($rows);
  }


}


