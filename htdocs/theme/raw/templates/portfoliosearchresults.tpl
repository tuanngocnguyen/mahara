{foreach from=$data item=result}
    <div class="list-group-item">
    {if $result->type == 'artefact'}
        {if $result->thumb}
            <span class="artefact-img-30"><img src="{$result->thumb}" alt="" class="artefact-img"></span>
            <h2 class="title list-group-item-heading text-inline">
            {if $result->link}
                <a href="{$WWWROOT}{$result->link}">
                    {$result->title|str_shorten_html:50:true|safe}
                </a>
            {else}
                {$result->title|str_shorten_html:50:true|safe}
            {/if}
        {else}
            <h2 class="title list-group-item-heading text-inline">
            <span class="icon left float-start icon-{$result->typestr}" role="presentation" aria-hidden="true"></span>
            {if $result->link}
                <a href="{$WWWROOT}{$result->link}">
                    {$result->title|str_shorten_html:50:true|safe}
                </a>
            {else}
                {$result->title|str_shorten_html:50:true|safe}
            {/if}
        {/if}

            <span class="artefacttype text-midtone text-regular">
            {if $result->artefacttype == "blogpost"}
                ({str tag=blogpost section=search.elasticsearch})
            {elseif $result->artefacttype == "forumpost"}
                ({str tag=forumpost section=search.elasticsearch})
            {elseif $result->artefacttype == "resume"}
                ({str tag=resume section=search.elasticsearch})
            {elseif $result->artefacttype == "wallpost"}
                ({str tag=wallpost section=search.elasticsearch})
            {else}
                ({$result->typelabel})
            {/if}
            </span>
        </h2>
        <div class="row">
            <div class="col-md-7">
                <div class="detail">
                {$result->description|str_shorten_html:140:true|safe}
                </div>
                <!-- TAGS -->
                {if is_array($result->tags) && count($result->tags) > 0}
                <div class="tags text-small">
                    {str tag=tags}: {list_tags tags=$result->tags owner=$owner view=$result->viewid}
                </div>
                {/if}
            </div>
            <div class="col-md-5">
            <!-- VIEWS -->
            {if is_array($result->views) && count($result->views) > 0}
                <div class="usedon">
                {if count($result->views) > 1}
                    {str tag=usedonpages section=search.elasticsearch}:
                {else}
                    {str tag=usedonpage section=search.elasticsearch}:
                {/if}
                    <ul class="list-group list-unstyled">
                    {foreach from=$result->views key=id item=view}
                    <li>
                        <a href="{$WWWROOT}view/view.php?id={$id}">{$view|str_shorten_html:50:true|safe}</a>
                        <!-- Profile artefact can only be displayed in views -->
                        {if $view->type != "profile"}
                            <span class="viewartefact">[
                                <a href="{$WWWROOT}view/view.php?id={$id}&modal=1&artefact={$result->id}">
                                    {str tag=viewartefact}
                                    {if $result->artefacttype == "blogpost"}
                                        {str tag=blogpost section=search.elasticsearch}
                                    {elseif $result->artefacttype == "forumpost"}
                                        {str tag=forumpost section=search.elasticsearch}
                                    {elseif $result->artefacttype == "resume"}
                                        {str tag=resume section=search.elasticsearch}
                                    {elseif $result->artefacttype == "wallpost"}
                                        {str tag=wallpost section=search.elasticsearch}
                                    {elseif $result->artefacttype == "blog"}
                                        {str tag=blog section=search.elasticsearch}
                                    {elseif $result->artefacttype == "html"}
                                        {str tag=html section=search.elasticsearch}
                                    {else}
                                        {$result->artefacttype|lower}
                                    {/if}
                                </a>]
                            </span>
                        {/if}
                    </li>
                    {/foreach}
                    </ul>
                </div>
            {/if}
            </div>
        </div>
        {elseif $result->type == 'blocktype'}
        <h2 class="title list-group-item-heading text-inline">
        <span class="icon left float-start icon-{$result->typestr}" role="presentation" aria-hidden="true"></span>
        {if $result->link}
            <a href="{$WWWROOT}{$result->link}">
                {$result->title|str_shorten_html:50:true|safe}
            </a>
        {else}
            {$result->title|str_shorten_html:50:true|safe}
        {/if}
        </h2>
        <span class="artefacttype text-midtone">
            ({$result->typelabel})
        </span>
        <div class="row">
            <div class="col-md-7">
                <div class="detail">
                {$result->description|str_shorten_html:140:true|safe}
                </div>
                <!-- TAGS -->
                {if is_array($result->tags) && count($result->tags) > 0}
                <div class="tags text-small">
                    {str tag=tags}: {list_tags tags=$result->tags owner=$owner view=$result->viewid}
                </div>
                {/if}
            </div>
            <div class="col-md-5">
            <!-- VIEWS -->
            {if is_array($result->views) && count($result->views) > 0}
                <div class="usedon">
                    {str tag=usedonpage section=search.elasticsearch}:
                    <ul class="list-group list-unstyled">
                    {foreach from=$result->views key=id item=view}
                    <li>
                        <a href="{$WWWROOT}view/view.php?id={$id}">{$view|str_shorten_html:50:true|safe}</a>
                    </li>
                    {/foreach}
                    </ul>
                </div>
            {/if}
            </div>
        </div>
        {else}
        <h2 class="list-group-item-heading title text-inline">
            <span class="icon left float-start icon-{$result->typestr}" role="presentation" aria-hidden="true"></span>
            <a href="{$result->url}">{$result->title}</a>
        </h2>
        <span class="tag-type text-midtone">({$result->typelabel})</span>
        <div class="row">
            <div class="col-md-7">
                <div class="text-small text-midtone">{$result->ctime}</div>
                <div class="detail">
                    {$result->description|str_shorten_html:100|strip_tags|safe}
                </div>
                {if is_array($result->tags) && count($result->tags) > 0}
                <div class="tags text-small">
                    {str tag=tags}: {list_tags tags=$result->tags owner=$owner view=$result->viewid}
                </div>
                {/if}
            </div>
            <div class="col-md-5">
                {if is_array($result->views) && count($result->views) > 0}
                    <div class="usedon">
                    {if count($result->views) > 1}
                        {str tag=views}:
                    {else}
                        {str tag=view}:
                    {/if}
                    {foreach from=$result->views key=id item=view name=views}
                        <a href="{$WWWROOT}view/view.php?id={$id}">{$view|str_shorten_html:50:true|safe}</a>{if !$.foreach.views.last}, {/if}
                    {/foreach}
                    </div>
                {/if}
                {if $result->viewtags}
                    <div class="tags text-small">
                        {str tag=viewtags}: {list_tags tags=$result->viewtags owner=$owner view=$result->viewid}
                    </div>
                {/if}
            </div>
        </div>
        {/if}
    </div>
{/foreach}
