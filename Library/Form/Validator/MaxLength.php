<?php
/**
 * Copyright Eli White & SaroSoftware 2010
 * Last Modified: 3/26/2010
 * 
 * This file is part of Saros Framework.
 * 
 * Saros Framework is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Saros Framework is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Saros Framework.  If not, see <http://www.gnu.org/licenses/>.
 *
 * This class makes sure a string is less than a certain length
 */
class Library_Form_Validator_MaxLength extends Library_Form_Validator
{
	protected $maxLength;

	protected $errorMessages = array(
		"tooLong" => "Your string must be at most {::max::} characters",
	);
	
	protected $errorHolders = array(
		"max"	=> "maxLength"
	);
	
	function __construct($options)
	{
		if (!isset($options[0]))
			throw new Exception("You must set a Max Length on the Validator");
					
		$this->maxLength = $options[0];
	}
	
	public function isValid($value)
	{
		if (strlen($value) <= $this->maxLength)
			return true;
			
		$this->setError("tooLong");
		return false;
	}
}
?>