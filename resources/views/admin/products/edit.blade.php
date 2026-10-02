@extends('layouts.admin')

@section('title', 'Edit Produk')

@section('content')
    <x-admin.page-header title="Edit produk" />
    @include('admin.products._form', ['product' => $product, 'action' => route('admin.products.update', $product)])
@endsection
