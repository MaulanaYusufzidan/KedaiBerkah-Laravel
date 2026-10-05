@extends('layouts.admin')

@section('title', 'Edit Area Pengiriman')

@section('content')
    <x-admin.page-header title="Edit area pengiriman" />
    @include('admin.delivery-areas._form', ['area' => $area, 'action' => route('admin.delivery-areas.update', $area)])
@endsection
