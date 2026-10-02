@extends('layouts.admin')

@section('title', 'Edit Kategori')

@section('content')
    <x-admin.page-header title="Edit kategori" />
    @include('admin.categories._form', [
        'category' => $category,
        'action' => route('admin.categories.update', $category),
        'nextSortOrder' => $category->sort_order,
    ])
@endsection
