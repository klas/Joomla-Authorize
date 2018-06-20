<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\CMS\Authorize\Implementation;

use Joomla\CMS\Authorize\AuthorizeInterface;
use Joomla\CMS\Authorize\AuthorizeHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Access\Rules;


defined('JPATH_PLATFORM') or die;

/**
 * Joomla Legacy authorization implementation
 *
 * @since       __DEPLOY_VERSION__
 * @deprecated  No replacement, to be removed in 4.2. Use AuthorizeImplementationJoomla instead.
 */
class AuthorizeImplementationJoomlaLegacy extends AuthorizeImplementationJoomla implements AuthorizeInterface
{

	/**
	 * Array of rules for the asset
	 *
	 * @var    array
	 * @since  11.1
	 */
	protected static $assetRules = array();

	/**
	 * Array of permissions
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $permCache = array();

	/**
	 * Method to set a value Example: $access->set('items', $items);
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
			default:
				AuthorizeImplementationJoomla::__set($name, $value);
		}

		return $this;
	}

	/**
	 * Method for clearing static caches.
	 *
	 * @return  void
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function clearStatics()
	{
		self::$assetRules = array();
		self::$permCache = array();
		self::$rootAsset = null;

		$this->authorizationMatrix = null;

		// Legacy
		\JUserHelper::clearStatics();
	}

	/**
	 * Check if a user is authorised to perform an action, optionally on an asset.
	 *
	 * @param   integer  $actor      Id of the user/group for which to check authorisation.
	 * @param   mixed    $target     Integer asset id or the name of the asset as a string or array with this values.
	 *                               Defaults to the global asset node.
	 * @param   string   $action     The name of the action to authorise.
	 * @param   string   $actorType  Type of actor. User or group.
	 *
	 * @return  mixed  True if authorised and assetId is numeric/named. An array of boolean values if assetId is array.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function check($actor, $target, $action, $actorType)
	{
		// Sanitise inputs.
		$id = (int) $actor;

		if ($actorType == 'group')
		{
			$identities = \JUserHelper::getGroupPath($id);
		}
		else
		{
			// Get all groups against which the user is mapped.
			$identities = \JUserHelper::getGroupsByUser($id);
			array_unshift($identities, $id * -1);
		}

		$action = AuthorizeHelper::cleanAction($action);

		// Clean and filter - run trough setter
		$this->assetId = $target;

		// Copy value as empty does not fire getter
		$target = $this->assetId;

		// Default to the root asset node.
		if (empty($target))
		{
			$assets = Table::getInstance('Asset', 'JTable', array('dbo' => $this->db));
			$this->assetId = $assets->getRootId();
			$target = $this->assetId;
		}

		if (is_array($target))
		{
			$result = array();
			$rules = $this->getRules(true, null, $action);

			foreach ($target AS $assetId)
			{
				$result[$assetId] = $rules[$assetId]->allow($action, $identities);
			}

			return $result;
		}

		// Get the rules for the asset recursively to root if not already retrieved.
		if (empty(self::$assetRules[$target]))
		{
			// Cache ALL rules for this asset
			self::$assetRules[$target] = $this->getRules(true, null, null);
		}

		return self::$assetRules[$target]->allow($action, $identities);
	}

	/**
	 * Speed enhanced permission lookup function
	 * Returns JAccessRules object for an asset.  The returned object can optionally hold
	 * only the rules explicitly set for the asset or the summation of all inherited rules from
	 * parent assets and explicit rules.
	 *
	 * @param   boolean  $recursive  True to return the rules object with inherited rules.
	 * @param   array    $groups     Array of group ids to get permissions for
	 * @param   string   $action     Action name to limit results
	 *
	 * @return  Rules|array   AccessRules object for the asset or array of AccessRules objects.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function getRules($recursive = false, $groups = null, $action = null )
	{
		// Make a copy for later
		$actionForCache = $action;

		$cacheId = $this->getCacheId($recursive, $groups, $actionForCache);

		if (!isset(self::$permCache[$cacheId]))
		{
			$result = $this->getAssetPermissions($recursive, $groups, $actionForCache);

			// If no result get all permisions for root node and cache it!
			if (empty($result))
			{
				if (!isset(self::$rootAsset))
				{
					$this->getRootAssetPermissions();
				}

				$result = self::$rootAsset;
			}

			self::$permCache[$cacheId] = $this->mergePermissionsRules($result);
		}

		// Instantiate and return the JAccessRules object for the asset rules.
		$rulesArr = array();
		$rules = new Rules;

		foreach (self::$permCache[$cacheId] AS $searchedId => $searched)
		{
			$rules = new Rules;

			$rules->mergeCollection($searched);

			// If action was set return only this action's result
			$data = $rules->getData();

			if (isset($action) && isset($data[$action]))
			{
				$data = array($action => $data[$action]);
				$rules = new Rules($data);
			}

			$rulesArr[$searchedId] = $rules;
		}

		if (is_array($this->assetId))
		{
			return $rulesArr;
		}
		else
		{
			return $rules;
		}

	}

	/**
	 * Calculate internal cache id
	 *
	 * @param   boolean  $recursive  True to return the rules object with inherited rules.
	 * @param   array    $groups     Array of group ids to get permissions for
	 * @param   string   &$action    Action name used for id calculation
	 *
	 * @since  __DEPLOY_VERSION__
	 *
	 * @return string
	 */
	private function getCacheId($recursive, $groups, &$action)
	{
		// We are optimizing only view for frontend, otherwise 1 query for all actions is faster globaly due to cache

		/*
		 if ($action == 'core.view')
		{
			if (isset(self::$permCache[md5(serialize(array($this->assetId, $recursive, $groups, null)))]))
			{
				$action = null;
			}
		}
		else
		{
			$action = null;
		}
		*/

		$assetid = $this->assetId;
		static $overLimit = false;

		if ($overLimit || count($this->assetId) > $this->optimizeLimit)
		{
			$assetid = array();
			$overLimit = true;
		}

		$cacheId = md5(serialize(array($assetid, $recursive, $groups, $action)));

		return $cacheId;
	}

	/**
	 * Query permissions based on asset id.
	 *
	 * @param   boolean  $recursive  True to return the rules object with inherited rules.
	 * @param   array    $groups     Array of group ids to get permissions for
	 * @param   string   $action     Action name to limit results
	 *
	 * @since  __DEPLOY_VERSION__
	 *
	 * @return mixed   Db query result - the return value or null if the query failed.
	 */
	private function getAssetPermissions($recursive = false, $groups = array(), $action = null)
	{
		$forceIndex = $straightJoin = '';

		if (count($this->assetId) > $this->optimizeLimit)
		{
			$useIds = false;
		}
		else
		{
			$useIds = true;

			if ($this->db->getServerType() == 'mysql')
			{
				$straightJoin = 'STRAIGHT_JOIN ';

				// $forceIndex = 'FORCE INDEX FOR JOIN (`PRIMARY`)';
			}
		}

		$query = $this->db->getQuery(true);

		// Build the database query to get the rules for the asset.
		$query->from($this->db->qn('#__assets', 'a'));

		// If we want the rules cascading up to the global asset node we need a self-join.
		if ($recursive)
		{
			$query->join('', $this->db->qn('#__assets', 'b') . $forceIndex . ' ON a.lft BETWEEN b.lft AND b.rgt ');

			$prefix = 'b';
		}
		else
		{
			$prefix = 'a';
		}

		$query->select(
					$straightJoin . 'a.id AS searchid, a.name AS searchname,' . $prefix . '.lft AS resultid, ' . $prefix
					. '.rules, p.permission, p.value, ' . $this->db->qn('p') . '.' . $this->db->qn('ugroup')
				);

		$conditions = 'ON p.assetid = ' . $prefix . '.id';

		if (isset($groups) && $groups != array())
		{
			$conditions .= ' AND ' . $this->assetGroupQuery($groups);
		}

		if (isset($action))
		{
			$conditions .= ' AND p.permission = ' . $this->db->quote((string) $action);
		}

		$query->leftJoin($this->db->qn('#__permissions', 'p') . ' ' . $conditions);

		if ($useIds && $recursive)
		{
			$query->where('a.lft > -1 AND b.lft > -1 AND b.rgt > -1');
		}

		if ($useIds)
		{
			$assetwhere = $this->assetWhere();
			$query->where($assetwhere);
		}

		$this->db->setQuery($query);
		$result = $this->db->loadObjectList();

		return $result;
	}


	/**
	 * Query root asset permissions
	 *
	 * @since  __DEPLOY_VERSION__
	 *
	 * @return mixed   Db query result - the return value or null if the query failed.
	 */
	public function getRootAssetPermissions()
	{
		if (!isset(self::$rootAsset))
		{
			$query = $this->db->getQuery(true);

			$query  ->select('b.id AS searchid, b.name AS searchname, b.lft AS resultid,  b.rules, p.permission, p.value, '
						. $this->db->qn('p') . '.' . $this->db->qn('ugroup')
			)
			->from($this->db->qn('#__assets', 'b'))
			->leftJoin($this->db->qn('#__permissions', 'p') . ' ON b.id = p.assetid')
			->where('b.parent_id=0');
			$this->db->setQuery($query);

			self::$rootAsset  = $this->db->loadObjectList();
		}


		return self::$rootAsset;
	}

	/**
	 * Merge new permissions with old rules from assets table for backwards compatibility
	 *
	 * @param   object  $results  database query result object with permissions and rules
	 *
	 * @since  __DEPLOY_VERSION__
	 *
	 * @return  array   authorisation matrix
	 */
	private function mergePermissionsRules($results)
	{
		$mergedResult = array();

		foreach ($results AS $result)
		{
			if (isset($result->permission) && !empty($result->permission))
			{
				if (!isset($mergedResult[$result->searchid]))
				{
					$mergedResult[$result->searchid] = array();
					$mergedResult[$result->searchid][$result->resultid] = array();
				}

				if (!isset($mergedResult[$result->searchid][$result->resultid][$result->permission]))
				{
					$mergedResult[$result->searchid][$result->resultid][$result->permission] = array();
				}

				$mergedResult[$result->searchid][$result->resultid][$result->permission][$result->ugroup] = (int) $result->value;
				$mergedResult[$result->searchname][$result->resultid][$result->permission][$result->ugroup] = (int) $result->value;
			}
			elseif (isset($result->rules) && $result->rules != '{}')
			{
				$mergedResult[$result->searchid][$result->resultid] = json_decode((string) $result->rules, true);
			}
		}

		return $mergedResult;
	}


	/** Inject permissions filter in the database object
	 *
	 * @param   \JDatabaseQuery  &$query      Database query object to append to
	 * @param   string           $joincolumn  Name of the database column used for join ON
	 * @param   string           $action      The name of the action to authorise.
	 * @param   string           $orWhere     Appended to generated where condition with OR clause.
	 * @param   array            $groups      Array of group ids to get permissions for
	 *
	 * @return  mixed database query object or false if this function is not implemented
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function appendFilterQuery(\JDatabaseQuery &$query, $joincolumn, $action, $orWhere = null, $groups = null)
	{
		return false;
	}
}
