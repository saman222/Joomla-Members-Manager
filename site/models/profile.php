<?php
/**
 * @package    Joomla.Members.Manager
 *
 * @created    6th September, 2015
 * @author     Llewellyn van der Merwe <https://www.joomlacomponentbuilder.com/>
 * @github     Joomla Members Manager <https://github.com/vdm-io/Joomla-Members-Manager>
 * @copyright  Copyright (C) 2015. All Rights Reserved
 * @license    GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * Membersmanager Profile Model
 */
class MembersmanagerModelProfile extends JModelItem
{
	/**
	 * Model context string.
	 *
	 * @var        string
	 */
	protected $_context = 'com_membersmanager.profile';

	/**
	 * Model user data.
	 *
	 * @var        strings
	 */
	protected $user;
	protected $userId;
	protected $guest;
	protected $groups;
	protected $levels;
	protected $app;
	protected $input;
	protected $uikitComp;

	/**
	 * @var object item
	 */
	protected $item;

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @since   1.6
	 *
	 * @return void
	 */
	protected function populateState()
	{
		$this->app = JFactory::getApplication();
		$this->input = $this->app->input;
		// Get the itme main id
		$id = $this->input->getInt('id', null);
		$this->setState('profile.id', $id);

		// Load the parameters.
		$params = $this->app->getParams();
		$this->setState('params', $params);
		parent::populateState();
	}

	/**
	 * Method to get article data.
	 *
	 * @param   integer  $pk  The id of the article.
	 *
	 * @return  mixed  Menu item data object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$this->user = JFactory::getUser();
		// check if this user has permission to access item
		if (!$this->user->authorise('site.profile.access', 'com_membersmanager'))
		{
			$app = JFactory::getApplication();
			$app->enqueueMessage(JText::_('COM_MEMBERSMANAGER_NOT_AUTHORISED_TO_VIEW_PROFILE'), 'error');
			// redirect away to the default view if no access allowed.
			$app->redirect(JRoute::_('index.php?option=com_membersmanager&view=cpanel'));
			return false;
		}
		$this->userId = $this->user->get('id');
		$this->guest = $this->user->get('guest');
		$this->groups = $this->user->get('groups');
		$this->authorisedGroups = $this->user->getAuthorisedGroups();
		$this->levels = $this->user->getAuthorisedViewLevels();
		$this->initSet = true;

		$pk = (!empty($pk)) ? $pk : (int) $this->getState('profile.id');
		
		if ($this->_item === null)
		{
			$this->_item = array();
		}

		if (!isset($this->_item[$pk]))
		{
			try
			{

				// Get the medium encryption.
				$mediumkey = MembersmanagerHelper::getCryptKey('medium');
				// Get the encryption object.
				$medium = new FOFEncryptAes($mediumkey);
				// Get a db connection.
				$db = JFactory::getDbo();

				// Create a new query object.
				$query = $db->getQuery(true);

				// Get from #__membersmanager_member as a
				$query->select($db->quoteName(
			array('a.id','a.account','a.token','a.type','a.name','a.surname','a.email','a.main_member','a.user','a.profile_image','a.published','a.created_by','a.modified_by','a.created','a.modified','a.version','a.hits','a.checked_out'),
			array('id','account','token','type','name','surname','email','main_member','user','profile_image','published','created_by','modified_by','created','modified','version','hits','checked_out')));
				$query->from($db->quoteName('#__membersmanager_member', 'a'));

				// Get from #__membersmanager_type as d
				$query->select($db->quoteName(
			array('d.description','d.name'),
			array('type_description','type_name')));
				$query->join('LEFT', ($db->quoteName('#__membersmanager_type', 'd')) . ' ON (' . $db->quoteName('a.type') . ' = ' . $db->quoteName('d.id') . ')');

				// Get from #__membersmanager_member as aa
				$query->select($db->quoteName(
			array('aa.name','aa.surname','aa.email','aa.user','aa.token'),
			array('main_member_name','main_member_surname','main_member_email','main_member_user','main_member_token')));
				$query->join('LEFT', ($db->quoteName('#__membersmanager_member', 'aa')) . ' ON (' . $db->quoteName('a.main_member') . ' = ' . $db->quoteName('aa.id') . ')');

				// Get from #__users as c
				$query->select($db->quoteName(
			array('c.name','c.username','c.email'),
			array('user_name','user_username','user_email')));
				$query->join('LEFT', ($db->quoteName('#__users', 'c')) . ' ON (' . $db->quoteName('a.user') . ' = ' . $db->quoteName('c.id') . ')');

				// Get from #__users as f
				$query->select($db->quoteName(
			array('f.name','f.username','f.email'),
			array('main_user_name','main_user_username','main_user_email')));
				$query->join('LEFT', ($db->quoteName('#__users', 'f')) . ' ON (' . $db->quoteName('aa.user') . ' = ' . $db->quoteName('f.id') . ')');
				$query->where('a.id = ' . (int) $pk);
				$query->where('a.access IN (' . implode(',', $this->levels) . ')');

				// Reset the query using our newly populated query object.
				$db->setQuery($query);
				// Load the results as a stdClass object.
				$data = $db->loadObject();

				if (empty($data))
				{
					$app = JFactory::getApplication();
					// If no data is found redirect to default page and show warning.
					$app->enqueueMessage(JText::_('COM_MEMBERSMANAGER_NOT_FOUND_OR_ACCESS_DENIED'), 'warning');
					$app->redirect(JRoute::_('index.php?option=com_membersmanager&view=cpanel'));
					return false;
				}
			// Load the JEvent Dispatcher
			JPluginHelper::importPlugin('content');
			$this->_dispatcher = JEventDispatcher::getInstance();
				// Check if we can decode profile_image
				if (!empty($data->profile_image) && $mediumkey && !is_numeric($data->profile_image) && $data->profile_image === base64_encode(base64_decode($data->profile_image, true)))
				{
					// Decode profile_image
					$data->profile_image = rtrim($medium->decryptString($data->profile_image), "\0");
				}
				// Check if item has params, or pass whole item.
				$params = (isset($data->params) && MembersmanagerHelper::checkJson($data->params)) ? json_decode($data->params) : $data;
				// Make sure the content prepare plugins fire on type_description
				$_type_description = new stdClass();
				$_type_description->text =& $data->type_description; // value must be in text
				// Since all values are now in text (Joomla Limitation), we also add the field name (type_description) to context
				$this->_dispatcher->trigger("onContentPrepare", array('com_membersmanager.member.type_description', &$_type_description, &$params, 0));
				// Checking if type_description has uikit components that must be loaded.
				$this->uikitComp = MembersmanagerHelper::getUikitComp($data->type_description,$this->uikitComp);
				// set idMain_memberMemberB to the $data object.
				$data->idMain_memberMemberB = $this->getIdMain_memberMemberDdbb_B($data->id);

				// set data object to item.
				$this->_item[$pk] = $data;
			}
			catch (Exception $e)
			{
				if ($e->getCode() == 404)
				{
					// Need to go thru the error handler to allow Redirect to work.
					JError::raiseWaring(404, $e->getMessage());
				}
				else
				{
					$this->setError($e);
					$this->_item[$pk] = false;
				}
			}
		}

		// set name
		$this->_item[$pk]->name = MembersmanagerHelper::getMemberName($this->_item[$pk]->id, $this->_item[$pk]->user, $this->_item[$pk]->name, $this->_item[$pk]->surname);
		// Build the item slug
		$this->_item[$pk]->slug = (isset($this->_item[$pk]->token)) ? $this->_item[$pk]->id . ':' . $this->_item[$pk]->token : $this->_item[$pk]->id;
		$this->_item[$pk]->main_member_slug = (isset($this->_item[$pk]->main_member_token)) ? $this->_item[$pk]->main_member . ':' . $this->_item[$pk]->main_member_token : $this->_item[$pk]->main_member;
		// setup the link to profile
		$profile_link = MembersmanagerHelperRoute::getProfileRoute($this->_item[$pk]->slug);
		$this->_item[$pk]->profile_link = JRoute::_($profile_link);
		$this->_item[$pk]->main_member_profile_link = JRoute::_(MembersmanagerHelperRoute::getProfileRoute($this->_item[$pk]->main_member_slug));
		// set main member name
		$this->_item[$pk]->main_member_name = MembersmanagerHelper::getMemberName($this->_item[$pk]->main_member, $this->_item[$pk]->main_member_user, $this->_item[$pk]->main_member_name, $this->_item[$pk]->main_member_surname);
		// build a return path
		$this->_item[$pk]->return_path = urlencode(base64_encode($profile_link));
		// check if we have children members
		if (isset($this->_item[$pk]->idMain_memberMemberB) && MembersmanagerHelper::checkArray($this->_item[$pk]->idMain_memberMemberB))
		{
			foreach ($this->_item[$pk]->idMain_memberMemberB as $item)
			{
				// Build the item slug
				$item->slug = (isset($item->token)) ? $item->id . ':' . $item->token : $item->id;
				// set sub member name
				$item->name = MembersmanagerHelper::getMemberName($item->id, $item->user, $item->name, $item->surname);
				// setup the link to profile
				$profile_link = MembersmanagerHelperRoute::getProfileRoute($item->slug);
				// setup the link to profile
				$item->profile_link = JRoute::_($profile_link);
				// build a return path
				$item->return_path = urlencode(base64_encode($profile_link));
			}
		}

		return $this->_item[$pk];
	}

	/**
	 * Method to get an array of Member Objects.
	 *
	 * @return mixed  An array of Member Objects on success, false on failure.
	 *
	 */
	public function getIdMain_memberMemberDdbb_B($id)
	{
		// Get the medium encryption.
		$mediumkey = MembersmanagerHelper::getCryptKey('medium');
		// Get the encryption object.
		$medium = new FOFEncryptAes($mediumkey);

		// Get a db connection.
		$db = JFactory::getDbo();

		// Create a new query object.
		$query = $db->getQuery(true);

		// Get from #__membersmanager_member as b
		$query->select($db->quoteName(
			array('b.id','b.account','b.token','b.type','b.name','b.surname','b.email','b.main_member','b.user','b.profile_image','b.published','b.created_by','b.modified_by','b.created','b.modified','b.version','b.hits','b.ordering'),
			array('id','account','token','type','name','surname','email','main_member','user','profile_image','published','created_by','modified_by','created','modified','version','hits','ordering')));
		$query->from($db->quoteName('#__membersmanager_member', 'b'));
		$query->where('b.main_member = ' . $db->quote($id));

				// Get from #__membersmanager_type as e
				$query->select($db->quoteName(
			array('e.description','e.name'),
			array('type_description','type_name')));
				$query->join('LEFT', ($db->quoteName('#__membersmanager_type', 'e')) . ' ON (' . $db->quoteName('b.type') . ' = ' . $db->quoteName('e.id') . ')');

				// Get from #__users as g
				$query->select($db->quoteName(
			array('g.name','g.username','g.email'),
			array('user_name','user_username','user_email')));
				$query->join('LEFT', ($db->quoteName('#__users', 'g')) . ' ON (' . $db->quoteName('b.user') . ' = ' . $db->quoteName('g.id') . ')');

		// Reset the query using our newly populated query object.
		$db->setQuery($query);
		$db->execute();

		// check if there was data returned
		if ($db->getNumRows())
		{
			$items = $db->loadObjectList();

			// Convert the parameter fields into objects.
			foreach ($items as $nr => &$item)
			{
				// Check if we can decode profile_image
				if (!empty($item->profile_image) && $mediumkey && !is_numeric($item->profile_image) && $item->profile_image === base64_encode(base64_decode($item->profile_image, true)))
				{
					// Decode profile_image
					$item->profile_image = rtrim($medium->decryptString($item->profile_image), "\0");
				}
			}
			return $items;
		}
		return false;
	}


	/**
	 * Get the uikit needed components
	 *
	 * @return mixed  An array of objects on success.
	 *
	 */
	public function getUikitComp()
	{
		if (isset($this->uikitComp) && MembersmanagerHelper::checkArray($this->uikitComp))
		{
			return $this->uikitComp;
		}
		return false;
	}
}
