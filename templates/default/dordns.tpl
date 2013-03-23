<div class="span12">

{include file="$template/pageheader.tpl" title="Reverse DNS"}

{if $ipv4addresses.error}

<div class="alert alert-error textcenter">
    <p>{$ipv4addresses.error}</p>
</div>

{elseif $ipv4addresses.success}

<div class="alert alert-success textcenter">
    <p>{$ipv4addresses.success}</p>
</div>

{/if}

<p><a href="rdns.php">Go back to rDNS.</a></p>

</div>
