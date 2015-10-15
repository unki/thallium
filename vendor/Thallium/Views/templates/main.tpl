{*
 * This file is part of Thallium.
 *
 * Thallium, a PHP-based framework for web applications.
 * Copyright (C) <2015> <Andreas Unterkircher>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
*}
<h1 class="ui header"><i class="database icon"></i>Welcome to Thallium!</h1>
<div class="ui two column grid">

 <!-- left column -->
 <div class="column">
<i class="archive icon"></i>Recently archived documents
  <div class="ui very relaxed divided selection list">
{top10 type=archive}
   <div class="item">
    <i class="file text icon"></i>
    <div class="content">
     <div class="header">
      <a href="{get_url page=archive mode=show id=$item_safe_link}">{$item->document_title}</a>&nbsp;
      <a href="{get_url page=document mode=show id="document-$item_safe_link" file=$item->document_file_name}"><i class="search icon"></i></a>
     </div>
     <div class="description">added {$item->document_time|date_format:"%Y.%m.%d %H:%M"}.</div>
    </div>
   </div>
{/top10}
  </div>
 </div>

 <!-- right column -->
 <div class="column">
  <i class="wait icon"></i>Recently enqueued documents
  <div class="ui very relaxed divided selection list">
{if isset($pending_queue_items)}
{top10 type=queue}
   <div class="item">
    <i class="file text icon"></i>
    <div class="content">
     <a class="header" href="{get_url page=queue mode=show id=$item_safe_link file=$item->queue_file_name}">{$item->queue_file_name}</a>
     <div class="description">added {$item->queue_time|date_format:"%Y.%m.%d %H:%M"}.</div>
    </div>
   </div>
{/top10}
{else}
   <div class="item">
    <div class="content">No items pending in queue.</div>
   </div>
{/if}
  </div>
 </div>

</div>
