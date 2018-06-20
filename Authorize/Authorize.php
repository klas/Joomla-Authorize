<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\CMS\Authorize;

defined('JPATH_PLATFORM') or die;

use Joomla\String\StringHelper;
use Joomla\CMS\Authorize\AuthorizeInterface;


/**
 * Authorize class
 *
 * @since  __DEPLOY_VERSION__
 */
final class Authorize implements AuthorizeInterface
{

	/**
	 * Implementation object
	 * _ suffixed to force usage of getters and setters even from within the class, use property name without_ to get or set the value
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	private $implementation_ = null;

	/**
	 * Constructor
	 *
	 * @param   AuthorizeInterface  $implementation  Implementation object
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function __construct(AuthorizeInterface $implementation)
	{
		$this->implementation = $implementation;
	}


	/**
	 * Method to allow controlled property value setting;
	 *
	 * @param   string  $name   Name of the property
	 * @param   mixed   $value  Value to assign to the property
	 *
	 * @return  self
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __set($name, $value)
	{
		switch ($name)
		{
			case 'implementation':
				if ($value instanceof AuthorizeInterface)
				{
					$this->implementation_ = $value;
				}
				break;

			case 'authorizationMatrix':
				$this->implementation->authorizationMatrix = $value;
				break;

			default:
				$this->implementation->$name = $value;
		}

		return $this;
	}

	/**
	 * Method to get the value
	 *
	 * @param   string  $key  Key to search for in the data array
	 *
	 * @return  mixed   Value | null if doesn't exist
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __get($key)
	{
		if ($key == 'implementation')
		{
			return $this->implementation_;
		}

		return $this->implementation->$key;
	}

	/**
	 * Method to call otherwise inaccessible methods
	 *
	 * @param   string  $method      Method to run
	 * @param   mixed   $parameters  Parameters
	 *
	 * @return  mixed   Value | null if doesn't exist
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __call($method, $parameters)
	{
		if (method_exists($this->implementation, $method))
		{
			return call_user_func_array(array($this->implementation, $method), $parameters);
		}
		else
		{
			throw new \BadMethodCallException(sprintf('%s does not exist in %s', $method, get_class($this)));
		}
	}

	/**
	 * Check if actor is authorised to perform an action, optionally on an asset.
	 *
	 * @param   integer  $actor      Id of the actor for which to check authorisation.
	 * @param   mixed    $target     Subject of the check
	 * @param   string   $action     The name of the action to authorise.
	 * @param   string   $actorType  Type of actor.
	 *
	 * @return  mixed  True if authorised and assetId is numeric/named. An array of boolean values if assetId is array.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function check($actor, $target, $action, $actorType)
	{
		return $this->implementation->check($actor, $target, $action, $actorType);
	}
}
