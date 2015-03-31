<% require css(importexport/css/csvpreviewer.css) %>
<div class="csvpreviewer">
	<table class="csvpreviewer_table">
		<thead>
			<% if MapHeadings %>
				<%-- Dropdowns for mapping data --%>
				<tr>
					<% loop MapHeadings %>
						<th>
							$Dropdown
						</th>
					<% end_loop %>
				</tr>
			<% end_if %>
		</thead>
		<tbody>
			<% loop Rows %>
			    <tr>
			    	<% loop Columns %>
						<td>$Value</td>
			    	<% end_loop %>
			    </tr>
			<% end_loop %>
		</tbody>
	</table>
</div>