@extends('layouts.shopify_admin')
@section('head')
<script type="text/javascript">
    ShopifyApp.init({
    apiKey: '{{ env('SHOPIFY_KEY') }}',
    shopOrigin: 'https://{{ $store->domain }}',
    debug: false
    });
</script>
<link rel="stylesheet" href="https://sdks.shopifycdn.com/polaris/latest/polaris.css" />
@endsection

@section('content')
<body>
    @include('store.schedule')
</body>
@endsection