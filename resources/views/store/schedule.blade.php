
<div style="--top-bar-background:#00848e; --top-bar-color:#f9fafb; --top-bar-background-lighter:#1d9ba4;">
  <div class="Polaris-Page">
    <div class="Polaris-Page-Header">
      <div class="Polaris-Page-Header__TitleAndRollup">
        <div class="Polaris-Page-Header__Title">
          <div>
            <h1 class="Polaris-DisplayText Polaris-DisplayText--sizeLarge">Quarterly Subscription Schedule</h1>
          </div>
          <div></div>
        </div>
      </div>
    </div>
    <div class="Polaris-Page__Content">
      <div class="Polaris-Card">
        <div class="">
          <div class="Polaris-DataTable__Navigation"><button type="button" class="Polaris-Button Polaris-Button--disabled Polaris-Button--plain Polaris-Button--iconOnly" disabled="" aria-label="Scroll table left one column"><span class="Polaris-Button__Content"><span class="Polaris-Button__Icon"><span class="Polaris-Icon"><svg class="Polaris-Icon__Svg" viewBox="0 0 20 20" focusable="false" aria-hidden="true">
                      <path d="M12 16a.997.997 0 0 1-.707-.293l-5-5a.999.999 0 0 1 0-1.414l5-5a.999.999 0 1 1 1.414 1.414L8.414 10l4.293 4.293A.999.999 0 0 1 12 16" fill-rule="evenodd"></path>
                    </svg></span></span></span></button><button type="button" class="Polaris-Button Polaris-Button--plain Polaris-Button--iconOnly" aria-label="Scroll table right one column"><span class="Polaris-Button__Content"><span class="Polaris-Button__Icon"><span class="Polaris-Icon"><svg class="Polaris-Icon__Svg" viewBox="0 0 20 20" focusable="false" aria-hidden="true">
                      <path d="M8 16a.999.999 0 0 1-.707-1.707L11.586 10 7.293 5.707a.999.999 0 1 1 1.414-1.414l5 5a.999.999 0 0 1 0 1.414l-5 5A.997.997 0 0 1 8 16" fill-rule="evenodd"></path>
                    </svg></span></span></span></button></div>
          <div class="Polaris-DataTable">
            <div class="Polaris-DataTable__ScrollContainer">
              <table class="Polaris-DataTable__Table">
                <thead>
                  <tr>
                    <th data-polaris-header-cell="true" class="Polaris-DataTable__Cell Polaris-DataTable__Cell--fixed Polaris-DataTable__Cell--header" scope="col" style="height: 53px;">Name</th>
                    <th data-polaris-header-cell="true" class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text Polaris-DataTable__Cell--header" scope="col" style="height: 53px;">Product Id</th>
                    <th data-polaris-header-cell="true" class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text Polaris-DataTable__Cell--header" scope="col" style="height: 53px;">Start Date</th>
                    <th data-polaris-header-cell="true" class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text Polaris-DataTable__Cell--header" scope="col" style="height: 53px;">End Date</th>
                    <th data-polaris-header-cell="true" class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text Polaris-DataTable__Cell--header" scope="col" style="height: 53px;">Charge Date</th>
                    <th data-polaris-header-cell="true" class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text Polaris-DataTable__Cell--header" scope="col" style="height: 53px;">Ship Date</th>
                  </tr>
                </thead>
                <tbody>
                @foreach($schedules as $schedule)
                    <tr class="Polaris-DataTable__TableRow">
                        <th class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text Polaris-DataTable__Cell--fixed" scope="row" style="height: 72px;"><strong>{{ $schedule->name }}</strong></th>
                        <td class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text" style="height: 72px;">{{ $schedule->product_id }}</td>
                        <td class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text" style="height: 72px;">{{ \App\Helpers\Helper::convertDateTimeToAppDt($schedule->start_dt) }}</td>
                        <td class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text" style="height: 72px;">{{ \App\Helpers\Helper::convertDateTimeToAppDt($schedule->end_dt) }}</td>
                        <td class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text" style="height: 72px;">{{ \App\Helpers\Helper::convertDateTimeToAppDt($schedule->charge_dt) }}</td>
                        <td class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text" style="height: 72px;">{{ \App\Helpers\Helper::convertDateTimeToAppDt($schedule->ship_dt) }}</td>
                    </tr>
                @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="Polaris-Page__Content">
      <div class="Polaris-Card">
        <div class="">
          <h4 style="margin:25px"><strong>Shopify Products</strong></h4>
          <div class="Polaris-DataTable">
            <div class="Polaris-DataTable__ScrollContainer">
              <table class="Polaris-DataTable__Table">
                <thead>
                  <tr>
                    <th data-polaris-header-cell="true" class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text Polaris-DataTable__Cell--header" scope="col" style="height: 53px;">Name</th>
                    <th data-polaris-header-cell="true" class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text Polaris-DataTable__Cell--header" scope="col" style="height: 53px;">Product Id</th>
                  </tr>
                </thead>
                <tbody>
                @foreach($products as $product)
                    <tr class="Polaris-DataTable__TableRow">
                        <th class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text Polaris-DataTable__Cell--text" scope="row" style="height: 72px;"><strong>{{ $product['title'] }}</strong></th>
                        <td class="Polaris-DataTable__Cell Polaris-DataTable__Cell--text" style="height: 72px;">{{ $product['id'] }}</td>
                    </tr>
                @endforeach
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="Polaris-Page__Content">
      <div class="Polaris-Card">
        <div class="" style="margin:25px">
          <h4><strong>Recharge Webhooks</strong></h4>
          @foreach($rechargeWebhooks as $rechargeWebhook)
            <p>## {{ $rechargeWebhook->topic }} --> {{ $rechargeWebhook->address }}</p>
          @endforeach
          @if ($rechargeWebhooks->count() > 0)
          <button id="removeWebhooksBtn" type="button" style="margin:25px;float:right" class="Polaris-Button Polaris-Button--destructive"><span class="Polaris-Button__Content"><span>Remove ReCharge Webhooks</span></span></button>
          @else
          <button id="registerWebhooksBtn" type="button" style="margin:25px;float:right" class="Polaris-Button Polaris-Button--primary"><span class="Polaris-Button__Content"><span>Register ReCharge Webhooks</span></span></button>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
<script>
$('#removeWebhooksBtn').click(function()
{
    ShopifyApp.Modal.confirm("Warning! This will effectively keep the Subscription Manager app from modifying ReCharge data on callbacks. Are you sure you want to remove the ReCharge Webhooks?", function(result){
        if(result)
        window.top.location.href = "{{route('webhooks.recharge.remove')}}";
    });
});
$('#registerWebhooksBtn').click(function()
{
    ShopifyApp.Modal.confirm("Are you sure you want to register ReCharge webhooks?", function(result){
        if(result)
          window.top.location.href = "{{route('webhooks.recharge.register')}}";
    });
});
</script>