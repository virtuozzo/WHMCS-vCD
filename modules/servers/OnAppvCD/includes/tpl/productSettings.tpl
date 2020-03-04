{assign var="selectedServer" value="`$productOptions.1`"}

<span class="oeu-container">
<script>OnAppModuleName = '{$moduleName}';</script>

<link href="../modules/servers/{$moduleName}/includes/css/adminArea.css" rel="stylesheet" type="text/css"/>
<script type="text/javascript" src="../modules/servers/{$moduleName}/includes/js/adminArea.js?"></script>


<link href="../modules/servers/{$moduleName}/includes/css/chosen/bootstrap-chosen.css" rel="stylesheet" type="text/css"/>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/chosen/1.4.2/chosen.jquery.min.js"></script>

{if $error}
	<div class="errorbox">
		<strong>
			<span class="title">
				{$lang->ErrorTitle}
			</span>
		</strong><br>
		<span class="oeu-error">{$error}</span>
	</div>
{else}
    {if $warning}
        <div class="errorbox">
            <strong>
                <span class="title">
                    {$lang->WarningTitle}
                </span>
            </strong><br>
            <span class="oeu-error">{$warning}</span>
        </div>
    {/if}
	<table class="form oeu" width="100%" border="0" cellspacing="2" cellpadding="3">
		<!-- server -->
		<tr>
			<td class="fieldlabel" style="width: 150px;">
				{assign var="itemName" value="Server"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="packageconfigoption[1]" required>
					<option value=""></option>
					{foreach from=$servers key=ID item=server}
						{if $productOptions.1 == $ID}
							{assign var="selected" value="selected"}
						{else}
							{assign var="selected" value=""}
						{/if}
						<option value="{$ID}" {$selected}>{$server->Name}</option>
					{/foreach}
				</select>
				{if $selectedServer}
					<button class="btn btn-default btn-sm pull-right1" id="oeu-reset-cache">
						{$lang->RefreshServerData}
					</button>
				{/if}

				<input type="hidden" name="{$moduleName}_ResetServerCache" class="oeu-reset-cache" value="">
				<input type="hidden" name="{$moduleName}_Prev" value="{$productSettingsJSON}">
				<input type="hidden" name="{$moduleName}_Skip" value="" id="{$moduleName}_Skip">
				<input type="hidden" name="{$moduleName}_Server" value="{$selectedServer}">
			</td>
		</tr>
	{if $selectedServer}
		<!-- holders for billing plan fields -->
		<tr class="hidden">
			<td colspan="2">
				<select id="bp-regular">
					{capture name="c1" assign="BPRegular"}
						<option value=""></option>
						{foreach from=$servers->$selectedServer->BillingPlans key=ID item=name}
							{if $productSettings->BillingPlanDefault == $ID}
								{assign var="selected" value="selected"}
							{else}
								{assign var="selected" value=""}
							{/if}
							<option value="{$ID}" {$selected}>{$name}</option>
						{/foreach}
					{/capture}
					{$BPRegular}
				</select>
				<select id="bp-company">
					{capture name="c1" assign="BPCompany"}
						<option value=""></option>
						{foreach from=$servers->$selectedServer->BillingCompanyPlans key=ID item=name}
							{if $productSettings->BillingPlanDefault == $ID}
								{assign var="selected" value="selected"}
							{else}
								{assign var="selected" value=""}
							{/if}
							<option value="{$ID}" {$selected}>{$name}</option>
						{/foreach}
					{/capture}
					{$BPCompany}
				</select>
			</td>
		</tr>
		<!-- hv -->
		<tr>
			<td class="fieldlabel">
				{assign var="itemName" value="HyperVisor"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="{$moduleName}[HyperVisor]" required>
					<option value=""></option>
					{foreach from=$servers->$selectedServer->HyperVisors key=ID item=name}
						{if $productSettings->HyperVisor == $ID}
							{assign var="selected" value="selected"}
						{else}
							{assign var="selected" value=""}
						{/if}
						<option value="{$ID}" {$selected}>{$name}</option>
					{/foreach}
				</select>
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
		<!-- timezones -->
		<tr>
			<td class="fieldlabel">
				{assign var="itemName" value="TimeZone"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="{$moduleName}[TimeZone]" required>
					<option value=""></option>
					{foreach from=$TimeZones key=ID item=name}
						{if $productSettings->TimeZone == $ID}
							{assign var="selected" value="selected"}
						{else}
							{assign var="selected" value=""}
						{/if}
						<option value="{$ID}" {$selected}>{$name}</option>
					{/foreach}
				</select>
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
		<!-- locale -->
		<tr>
			<td class="fieldlabel">
				{assign var="itemName" value="Locale"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="{$moduleName}[Locale]" required>
					<option value=""></option>
					{foreach from=$servers->$selectedServer->Locales key=ID item=name}
						{if $productSettings->Locale == $ID}
							{assign var="selected" value="selected"}
						{else}
							{assign var="selected" value=""}
						{/if}
						<option value="{$ID}" {$selected}>{$name}</option>
					{/foreach}
				</select>
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
		<!-- roles -->
		<tr>
			<td class="fieldlabel">
				{assign var="itemName" value="Roles"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="{$moduleName}[Roles][]" required>
					{foreach from=$servers->$selectedServer->Roles key=ID item=name}
						{if in_array($ID, $productSettings->Roles)}
							{assign var="selected" value="selected"}
						{else}
							{assign var="selected" value=""}
						{/if}
						<option value="{$ID}" {$selected}>{$name}</option>
					{/foreach}
				</select>
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
		<!-- billing plan default -->
		<tr>
			<td class="fieldlabel">
				{if $servers->$selectedServer->OnAppVersion > 6.1}
					{assign var="itemName" value="CompanyBucket"}
					{assign var="itemDescription" value="`$itemName`Description" }
				{else}
					{assign var="itemName" value="BillingPlan"}
					{assign var="itemDescription" value="`$itemName`Description" }
				{/if}
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="{$moduleName}[BillingPlanDefault]" required id="billing-plan">
					{if $productOptions.7 == 2}
						{$BPCompany}
					{else}
						{$BPRegular}
					{/if}
				</select>
				<span class="oeu-info">
					{$lang->$itemDescription->Default}
				</span>
			</td>
		</tr>
		<!-- billing plan group -->
		{if $productOptions.7 == 1}
			{assign var="groupDisabled" value="disabled"}
			<input type="hidden" name="{$moduleName}[OrganizationType]" value="2">
			<tr class="collapse" id="group-bp-row">
		{else}
			{assign var="groupDisabled" value=""}
			<tr id="group-bp-row">
		{/if}
			<td class="fieldlabel">
				{if $servers->$selectedServer->OnAppVersion > 6.1}
					{assign var="itemName" value="GroupBuckets"}
					{assign var="itemDescription" value="`$itemName`Description" }
				{else}
					{assign var="itemName" value="GroupBillingPlans"}
					{assign var="itemDescription" value="`$itemName`Description" }
				{/if}




				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="{$moduleName}[GroupBillingPlans][]" multiple required {$groupDisabled}>
					<option value=""></option>
					{foreach from=$servers->$selectedServer->BillingPlans key=ID item=name}
						{if in_array($ID, $productSettings->GroupBillingPlans)}
							{assign var="selected" value="selected"}
						{else}
							{assign var="selected" value=""}
						{/if}
						<option value="{$ID}" {$selected}>{$name}</option>
					{/foreach}
				</select>
				<span class="oeu-info">
					{$lang->$itemDescription->Default}
				</span>
			</td>
		</tr>
		<!-- groups -->
		{if $productOptions.7 == 2}
			<input type="hidden" name="{$moduleName}[OrganizationType]" value="2">
			{assign var="groupDisabled" value="disabled"}
			<tr class="collapse" id="group-row">
		{else}
			{assign var="groupDisabled" value=""}
			<tr id="group-row">
		{/if}
			<td class="fieldlabel">
				{assign var="itemName" value="UserGroups"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="{$moduleName}[UserGroups]" required {$groupDisabled}>
					{foreach from=$servers->$selectedServer->UserGroups key=ID item=name}
						{if in_array($ID, $productSettings->UserGroups)}
							{assign var="selected" value="selected"}
						{else}
							{assign var="selected" value=""}
						{/if}
						<option value="{$ID}" {$selected}>{$name}</option>
					{/foreach}
				</select>
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
		<tr>
			<td colspan="2" class="fieldarea">
				<span class="oeu-info">
					{$lang->CommonSettings}
				</span>
			</td>
		</tr>
		<!-- organization type -->
		<tr>
			<td class="fieldlabel">
				{assign var="itemName" value="OrganizationType"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="{$moduleName}[OrganizationType]" id="org-type" required {$groupDisabled}>
					<option value=""></option>
					{foreach from=$lang->OrganizationTypeVariants key=ID item=name}
						{assign var="counter" value="{$ID+1}"}
						{if $productOptions.7 != '' and $productOptions.7 == $counter}
							{assign var="selected" value="selected"}
						{else}
							{assign var="selected" value=""}
						{/if}
						<option value="{$counter}" {$selected}>{$name}</option>
					{/foreach}
				</select>
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
		<!-- billing type -->
		<tr>
			<td class="fieldlabel">
				{assign var="itemName" value="BillingType"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<select name="{$moduleName}[BillingType]" required>
					<option value=""></option>
					{foreach from=$lang->BillingTypeVariants key=ID item=name}
						{assign var="counter" value="{$ID+1}"}
						{if $productOptions.2 != '' and $productOptions.2 == $counter}
							{assign var="selected" value="selected"}
						{else}
							{assign var="selected" value=""}
						{/if}
						<option value="{$counter}" {$selected}>{$name}</option>
					{/foreach}
				</select>
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
		<!-- due days -->
		<tr>
			<td class="fieldlabel">
				{assign var="itemName" value="DueDays"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<input type="number" name="{$moduleName}[DueDays]" min="0" value="{$productOptions.5|default:0}" class="form-control input-sm">
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
		<!-- suspend days -->
		<tr>
			<td class="fieldlabel">
				{assign var="itemName" value="SuspendDays"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<input type="number" name="{$moduleName}[SuspendDays]" min="0" value="{$productOptions.3|default:7}" class="form-control input-sm">
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
		<!-- terminate days -->
		<tr>
			<td class="fieldlabel">
				{assign var="itemName" value="TerminateDays"}
				{assign var="itemDescription" value="`$itemName`Description" }
				{$lang->$itemName}
			</td>
			<td class="fieldarea">
				<input type="number" name="{$moduleName}[TerminateDays]" min="0" value="{$productOptions.6|default:14}" class="form-control input-sm">
				<span class="oeu-info">
					{$lang->$itemDescription}
				</span>
			</td>
		</tr>
	{/if}
	</table>
{/if}
