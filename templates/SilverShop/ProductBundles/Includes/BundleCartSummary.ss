<%-- Compact list of a bundle line's contents (required items + the extras this customer chose), for a
     cart/order row. Include it with the bundle ORDER ITEM in scope — in a cart item loop the current scope
     already is the order item:  <% include SilverShop\ProductBundles\BundleCartSummary %>
     Outputs nothing for non-bundle order items. --%>
<% if $FulfilmentItems %>
<ul class="bundle-cart-summary" style="list-style:none;margin:.25rem 0 0;padding:0;font-size:.85rem;color:#666">
    <% loop $FulfilmentItems %>
        <li>{$Quantity}&times; $BuyableTitle</li>
    <% end_loop %>
</ul>
<% end_if %>
