<div class="span12">

{include file="$template/pageheader.tpl" title="Reverse DNS"}

{if $ipv4addresses.error}
<div class="alert alert-error textcenter">
    <p>{$ipv4addresses.error}</p>
</div>

{else}

<form method="post" action="{$systemsslurl}dordns.php" class="form-stacked" name="frmrdns">

<table class="table table-striped table-framed">
	<thead>
		<tr>
			<th>IP Address</th>
			<th>Hostname</th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<td><input type="submit" name="submit_rdns" class="btn btn-primary btn-large" value="Save all changes" /></td>
			<td><a href="rdns.php" class="btn">Cancel</a></td>
		</tr>
	</tfoot>
	<tbody>
		{foreach item="hostname" key="ipv4" from=$ipv4addresses}
			<tr>
				<td>{$ipv4}</td>
				<td><input class="input-xlarge" name="{$ipv4}" id="hostname" type="text" value="{$hostname}" class="input-xxlarge" /></td>
			</tr>
		{foreachelse}
			<tr>
				<td colspan="7" class="textcenter">{$LANG.norecordsfound}</td>
			</tr>
		{/foreach}
	</tbody>
</table>
<input name="rdns_csrf_token" id="rdns_csrf_token" type="hidden" value="{$rdns_csrf_token}" />
<input name="serviceid" id="serviceid" type="hidden" value="{$serviceid}" />
</form>

{/if}

</div>
