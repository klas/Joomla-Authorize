<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\CMS\Authorize;

defined('JPATH_PLATFORM') or die;

/**
 * Authorize interface
 *
 * @since  __DEPLOY_VERSION__
 */
interface AuthorizeInterface
{

	/**
	 * Check if actor is authorised to perform an action, optionally on an asset.
	 *
	 * @param   integer  $actor      Id of the actor for which to check authorisation.
	 * @param   mixed    $target     Subject of the check
	 * @param   string   $action     The name of the action to authorise.
	 * @param   string   $actorType  Type of actor.
	 *
	 * @return  boolean  True if authorised.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function check($actor, $target, $action, $actorType);

}
