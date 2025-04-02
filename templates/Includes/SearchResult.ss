
<% cached $ClassName, $ID, $LastEdited %>
	<article class="search-result">

	    <h3 class="title">
	    	<a href="$Link">$Title</a>
	    </h3>    

	    <ul class="details">
	        <% if $File %>
	            <li class="type">
	                $Extension
	            </li>
	            <li class="size">
	                $Size
	            </li>
	    	<% else_if $Parent %>
	            <li class="parent">
	                <a href="{$Parent.Link}">
	                	$Parent.Title
	                </a>
	            </li>
	        <% end_if %>
	        <li class="edited">
	            Updated $LastEdited.Format(MMMM d), $LastEdited.Format(y)
	        </li>
	    </ul>

	    <div class="preview">
	        <p>
	        	<% if $Description %>
	        		$Description
	        	<% else_if $Content %>
	        		$Content.LimitCharactersToClosestWord(40)
	        	<% end_if %>
	        </p>
	    </div>
	    
	</article>
<% end_cached %>
