{include file="header.tpl"}
<p class="lead view-description">{str tag=behatvariablesdesc section=admin}</p>
{if !$hascore}
<div class="alert alert-warning">{str tag=behatnocoresteps section=admin}</div>
{/if}
{if $data}
<div class="pieform">
    {assign var="prevkey" value=''}
    {foreach from=$data key=k item=v name=data}
        <div class="form-group collapsible-group">
        {if $prevkey !== $k}
            <fieldset id="fs_{$dwoo.foreach.data.index}" class="pieform-fieldset collapsible {if $dwoo.foreach.data.last} last{/if}">
                <legend>
                    <a id="link_{$dwoo.foreach.data.index}" class="collapsed" href="#behatfield-{$dwoo.foreach.data.index}" data-bs-toggle="collapse" aria-expanded="false" aria-controls="#behatfield-{$dwoo.foreach.data.index}">
                        {$k}
                        <span class="icon icon-chevron-down collapse-indicator right float-end"></span>
                    </a>
                </legend>
                <div id="behatfield-{$dwoo.foreach.data.index}" class="fieldset-body collapse">
        {/if}
        {if $v == 'notused'}
            {str tag="behatstepnotused" section="admin"}
        {else}
            {foreach from=$v key=sk item=sv name=subdata}
                <div id="fs_{$dwoo.foreach.data.index}_{$dwoo.foreach.subdata.index}" class="pieform-fieldset collapsible {if $dwoo.foreach.v.last} last{/if}">
                    <div><a id="link_{$dwoo.foreach.data.index}_{$dwoo.foreach.subdata.index}" class="collapsed" href="#behatfield-{$dwoo.foreach.data.index}-{$dwoo.foreach.subdata.index}" data-bs-toggle="collapse" aria-expanded="false" aria-controls="#behatfield-{$dwoo.foreach.data.index}-{$dwoo.foreach.subdata.index}">{str tag=behatmatchingrows section=admin arg1=count($sv)} {$sk}.feature</a></div>
                    <div id="behatfield-{$dwoo.foreach.data.index}-{$dwoo.foreach.subdata.index}" class="fieldset-body collapse list-group">
                    {foreach $sv key=row item=value}
                        <div>line {$row}: {$value} </div>
                    {/foreach}
                    </div>
                </div>
            {/foreach}
        {/if}
        {if $prevkey !== $k}
                </div>
            </fieldset>
            {$prevkey = $k}
        {/if}
        </div>
    {/foreach}
</div>
{else}
    {str tag=nobehatfeaturefiles section=admin}
{/if}
{include file="footer.tpl"}
