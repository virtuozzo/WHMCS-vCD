<link rel="stylesheet" href="modules/servers/{$moduleName}/includes/css/clientArea.css"/>

<script>
	var LANG = {$jsLang};
</script>

<div id="gotocp">
	<h3 class="aleft1">{$lang->ManageMyCloud}</h3>

	<table class="table table-striped table-condensed">
		<tr>
			<td class="col-md-3">{$lang->CPURL}</td>
			<td><a href="{$serverURL}" target="_blank">{$serverURL}</a></td>
		</tr>
		<tr>
			<td>{$lang->UserName}</td>
			<td>{$params->username}</td>
		</tr>
		<tr>
			<td>{$lang->Password}</td>
			<td>
				<input type="password" class="preview-password" value="{$params->password}" disabled>
			</td>
		</tr>
	</table>

	<form action="modules/servers/{$moduleName}/includes/php/getCP.php" method="post" id="gotocpform" autocomplete="off"
		  target="_blank">
		<input type="hidden" id="authenticity_token" name="authenticity_token" value="{$token}" autocomplete="off">
		<button type="submit" class="btn btn-default">
			{$lang->OpenCP}
		</button>
	</form>

	<div class="alert">
		<a href="#" class="close" aria-label="close" onclick="$('.alert').hide('fast');return false;">&times;</a>
		<span></span>
	</div>
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
						<button type="button" data-process="{$lang->Loading}" class="btn btn-default">
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
    <tbody id="app" class="text-left">
		<!-- vue template -->
		{if $organizationType == 1}
			{include 'clientArea/single-org.tpl'}
		{else}
			{include 'clientArea/multi-org.tpl'}
		{/if}
		<!-- vue template -->
    </tbody>
</table>


{literal}
<script>
	$( document ).ready( function() {
		var body = $("body");
		//check if bootstrap is loaded
		if(typeof($.fn.modal) === 'undefined') {
			body.append('<script src="assets/js/jquery.min.js"></sc'+'ript>');
			body.append('<style type="text/css">@import url("/assets/css/bootstrap.min.css");</style>');
			body.append('<script src="assets/js/bootstrap.min.js"><'+'/'+'script>');
			var customCss='<style>#top_container{height: 63px;}body{background-color: #369;}</style>';
			body.append(customCss);
			window.onappvcd_serviceid = {/literal}{$params->serviceid}{literal};

			body.append('<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment-with-locales.js"></sc'+'ript>');
			body.append('<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.37/css/bootstrap-datetimepicker.min.css"/>');
			body.append('<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.37/js/bootstrap-datetimepicker.min.js"></sc'+'ript>');

			body.append('<link rel="stylesheet" href="//cdn.rawgit.com/jaunesarmiento/prevue.js/master/src/css/font-awesome-eyes.css"/>');
			body.append('<script type="text/javascript" src="//cdn.rawgit.com/jaunesarmiento/prevue.js/master/src/js/jquery.prevue.min.js"></sc'+'ript>');

			body.append('<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/vue/1.0.16/vue.min.js"></sc'+'ript>');

		}

	});
</script>
{/literal}

<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment-with-locales.js"></script>

<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.37/css/bootstrap-datetimepicker.min.css"/>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.17.37/js/bootstrap-datetimepicker.min.js"></script>

<link rel="stylesheet" href="//cdn.rawgit.com/jaunesarmiento/prevue.js/master/src/css/font-awesome-eyes.css"/>
<script type="text/javascript" src="//cdn.rawgit.com/jaunesarmiento/prevue.js/master/src/js/jquery.prevue.min.js"></script>

<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/vue/1.0.16/vue.min.js"></script>

{if $organizationType == 1}
	{literal}
		<script>
			$( document ).ready( function() {
				$("body").append('<script type="text/javascript" src="modules/servers/{/literal}{$moduleName}{literal}/includes/js/clientArea/single-org.js"></sc'+'ript>');
			});
		</script>
	{/literal}
{else}
	{literal}
		<script>
			$( document ).ready( function() {
				$("body").append('<script type="text/javascript" src="modules/servers/{/literal}{$moduleName}{literal}/includes/js/clientArea/multi-org.js"></sc'+'ript>');
			});
		</script>
	{/literal}
{/if}

{literal}
	<script>
		$( document ).ready( function() {
			$("body").append('<script type="text/javascript" src="modules/servers/{/literal}{$moduleName}{literal}/includes/js/clientArea/main.js"></sc'+'ript>');
		});
	</script>
{/literal}