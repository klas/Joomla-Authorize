# Joomla Authorize

**Authorization library for Joomla.**

Usage:

$implementation = new AuthorizeImplementationJoomla();

$authObj        = new Authorize($implementation);

$result         = $authObj->check($actor, $target, $action, $actorType);