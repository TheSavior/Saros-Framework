<?php
/**
 * This class helps create a list of style tags in layout headers
 *
 * @copyright Eli White & SaroSoftware 2010
 * @license http://www.gnu.org/licenses/gpl.html GNU GPL
 * 
 * @package SarosFramework
 * @author Eli White
 * @link http://sarosoftware.com
 * @link http://github.com/TheSavior/Saros-Framework
 */
class Saros_Display_Helpers_HeadStyles
{
	public $styles = array();

	public function addStyle($name)
	{
		$style = "Application/".$GLOBALS['registry']->router->getModule()."/Views/StyleSheets/".$name.".css";
		if (!file_exists(ROOT_PATH.$style))
			throw new Saros_Exception("Stylesheet ".$name." could not be found at ".$style);

		$this->styles[] = $GLOBALS['registry']->config["siteUrl"].$style;

		return $this;
	}

	public function __toString()
	{
		$output = "";
		foreach ($this->styles as $style)
		{
			$output .= '<link rel="stylesheet" type="text/css" href="'.$style.'" />';
		}

		return $output;
	}
}
?>