<div class="span12">

{include file="$template/pageheader.tpl" title="Reverse DNS"}

{if $services.error}
<div class="alert alert-error textcenter">
    <p>{$services.error}</p>
</div>

{else}

<table class="table table-striped table-framed">
    <thead>
        <tr>
            <th>{$LANG.orderproduct}</th>
            <th>{$LANG.clientareastatus}</th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    <tbody>
{foreach from=$services item=service key=servicekey}
{if $service.serverid == "1" && $service.status == "Active"}
        <tr>
            <td><strong>{$service.groupname} - {$service.name}</strong>{if $service.domain}<br /><a href="http://{$service.domain}" target="_blank">{$service.domain}</a>{/if}</td>
            <td><span class="label {$service.status}">{$service.status}</span></td>
            <td>
                <a href="rdns.php?q={$service.id}" class="btn">Edit rDNS</a>
            </td>
        </tr>
{/if}
{foreachelse}
        <tr>
            <td colspan="6" class="textcenter">{$LANG.norecordsfound}</td>
        </tr>
{/foreach}
    </tbody>
</table>

{/if}

</div>
