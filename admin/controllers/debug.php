<?php
/**
 * @component Pay per Download component
 * @author Ratmil Torres
 * @copyright (C) Ratmil Torres
 * @license GNU/GPL http://www.gnu.org/copyleft/gpl.html
**/
defined( '_JEXEC' ) or
die( 'Direct Access to this location is not allowed.' );

/**
 * @author		Ratmil 
 * http://www.ratmilwebsolutions.com
*/

require_once(JPATH_COMPONENT.'/controllers/ppd.php');
require_once(JPATH_COMPONENT.'/data/gentable.php');
require_once(JPATH_COMPONENT.'/html/vdatabind.html.php');
require_once(JPATH_COMPONENT.'/html/vdatabindmodel.html.php');

/************************************************************
Class to manage sectors
*************************************************************/
class DebugForm extends PPDForm
{
	/**
	Class constructor
	*/
	function __construct()
	{
		parent::__construct();
		$this->context = 'com_payperdownload.debug';
		$this->formTitle = $this->toolbarTitle = JText::_('PAYPERDOWNLOADPLUS_DEBUG');
		$this->toolbarIcon = 'generic.png';
	}
	
	/**
	Create the elements that define how data is to be shown and handled. 
	*/
	function createDataBinds()
	{
		if($this->dataBindModel == null)
		{
			$option = JRequest::getVar('option');
		
			$this->dataBindModel = new VisualDataBindModel();
			$this->dataBindModel->setKeyField("debug_id");
			$this->dataBindModel->setTableName("#__payperdownloadplus_debug");
			
			$bind = new VisualDataBind('debug_text', JText::_('PAYPERDOWNLOADPLUS_DEBUG_TEXT'));
			$bind->setColumnWidth(80);
			$this->dataBindModel->addDataBind( $bind );
			
			$bind = new VisualDataBind('debug_time', JText::_('PAYPERDOWNLOADPLUS_DEBUG_TIME'));
			$bind->setColumnWidth(10);
			$this->dataBindModel->addDataBind( $bind );
		}
	}
	
	function createToolbar($task, $option)
	{
		JToolBarHelper::title( $this->toolbarTitle, $this->toolbarIcon );
	}
}
?>