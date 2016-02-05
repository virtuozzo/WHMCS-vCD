<link rel="stylesheet" href="modules/servers/{$moduleName}/includes/css/clientArea.css"/>

<script>
	var LANG = {$jsLang};
</script>

<div id="gotocp">
	<h3 class="aleft1">{$lang->ManageMyCloud}</h3>

	<div class="alert">
		<a href="#" class="close" aria-label="close" onclick="$('.alert').hide('fast');return false;">&times;</a>
		<span></span>
	</div>

	<form action="modules/servers/{$moduleName}/includes/php/getCP.php" method="post" id="gotocpform" autocomplete="off"
		  target="_blank">
		<input type="hidden" id="authenticity_token" name="authenticity_token" value="{$token}" autocomplete="off">
		<button type="submit" class="btn btn-default">
			{$lang->OpenCP}
		</button>
		<button type="button" class="btn btn-default" data-loading-text="{$lang->Processing}" id="change-password">
			{$lang->GenerateNewPassword}
		</button>
		{if $additional->isTrial}
			<button type="button" class="btn btn-default" data-loading-text="{$lang->Processing}" id="convert-trial">
				{$lang->ConvertTrial}
			</button>
		{/if}
	</form>
</div>

<h3 id="user-stat">{$lang->OutstandingDetails}</h3>
<table class="table table-bordered table-striped table-condensed" id="stat_data">
    <thead>
		<tr>
			<th colspan="2">
				<div class="col-md-12 col-md-offset-1">
					<div class="col-md-4">
						<div class="input-group date" id="datetimepicker1">
							<input type="text" class="form-control" placeholder="{$lang->StartDate}..."/>
							<span class="input-group-addon">
								<span class="glyphicon glyphicon-calendar"></span>
							</span>
						</div>
					</div>
					<div class="col-md-4">
						<div class="input-group date" id="datetimepicker2">
							<input type="text" class="form-control" placeholder="{$lang->EndDate}..."/>
							<span class="input-group-addon">
								<span class="glyphicon glyphicon-calendar"></span>
							</span>
						</div>
					</div>
					<div class="col-md-2 text-left">
						<button type="button" id="loading-example-btn"
								data-loading-text="{$lang->Loading}" class="btn btn-default">
							{$lang->Apply}
						</button>
					</div>
				</div>
			</th>
		</tr>
        <tr id="error">
            <th colspan="2">
                {$lang->AJAXError}
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="span4">{$lang->VMCost}</td>
            <td id="vm_cost"></td>
        </tr>
        <tr>
            <td>{$lang->BackupsCost}</td>
            <td id="backup_cost"></td>
        </tr>
        <tr>
            <td>{$lang->MonitCost}</td>
            <td id="monit_cost"></td>
        </tr>
        <tr>
            <td>{$lang->StorageCost}</td>
            <td id="storage_disk_size_cost"></td>
        </tr>
        <tr>
            <td>{$lang->TemplatesCost}</td>
            <td id="template_cost"></td>
        </tr>
        <tr>
            <td>{$lang->BackupCountCost}</td>
            <td id="backup_count_cost"></td>
        </tr>
        <tr>
            <td>{$lang->BackupDiskCost}</td>
            <td id="backup_disk_size_cost"></td>
        </tr>
        <tr>
            <td>{$lang->TemplateCountCost}</td>
            <td id="template_count_cost"></td>
        </tr>
        <tr>
            <td>{$lang->TemplateDiskSizeCost}</td>
            <td id="template_disk_size_cost"></td>
        </tr>
        <tr>
            <td>{$lang->CustomerNetworkCost}</td>
            <td id="customer_network_cost"></td>
        </tr>
        <tr>
            <td>{$lang->EdgeGroupCost}</td>
            <td id="edge_group_cost"></td>
        </tr>
        <tr>
            <td>{$lang->TotalCost}</td>
            <td id="total_cost"></td>
        </tr>
    </tbody>
</table>

<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment-with-locales.js"></script>

<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.14.30/css/bootstrap-datetimepicker.min.css"/>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.14.30/js/bootstrap-datetimepicker.min.js"></script>

<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/accounting.js/0.3.2/accounting.min.js"></script>
<script type="text/javascript" src="modules/servers/{$moduleName}/includes/js/clientArea.js"></script>
