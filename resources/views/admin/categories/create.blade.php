@extends('layouts.admin')

@section('title', 'Tambah Kategori')

@section('content')
    <x-admin.page-header title="Tambah kategori" />
    @include('admin.categories._form', ['category' => null, 'action' => route('admin.categories.store')])
@endsection
