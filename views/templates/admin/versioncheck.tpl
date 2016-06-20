{*
 *            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 *                  Version 2, December 2004
 *
 * Copyright (C) 2016 Michael Dekker <prestashop@michaeldekker.com>
 *
 * Everyone is permitted to copy and distribute verbatim or modified
 * copies of this license document, and changing it is allowed as long
 * as the name is changed.
 *
 *           DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 * TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
 *
 *  @author    Michael Dekker <prestashop@michaeldekker.com>
 *  @copyright 2016 Michael Dekker
 *  @license   http://www.wtfpl.net/about/ Do What The Fuck You Want To Public License (WTFPL v2)
*}
<div class="panel">
	<h3><i class="icon icon-refresh"></i> {l s='Check for updates' mod='modselfupdate'}</h3>
	<p>
		<strong>{l s='Check if this module needs updates' mod='modselfupdate'}</strong><br />
	</p>
	{if $needsUpdate}
		<div class="alert alert-warning">
			{l s='This module needs to be updated to version %s' mod='mdstripe' sprintf=[$latestVersion]}
		</div>
	{else}
		<div class="alert alert-success">
			{l s='This module is up to date.' mod='modselfupdate'}
		</div>
	{/if}
	<a class="btn btn-default" href="{$baseUrl}&modselfupdateCheckUpdate=1"><i class="icon icon-search"></i> {l s='Check for updates' mod='modselfupdate'}</a>
	{if $needsUpdate}
		<a class="btn btn-default" href="{$baseUrl}&modselfupdateApplyUpdate=1"><i class="icon icon-refresh"></i> {l s='Update module' mod='modselfupdate'}</a>
	{/if}
</div>