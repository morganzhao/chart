@extends('layouts.app')

    @section('content')
    <div class="container">
              <div class="row justify-content-center">
                        <div class="card">
                                  <div class="card-header">文章详情</div>

                                            <div class="card-body">
                                                      <h1 class="text-center">{{ $blog->title }}</h1>

<p>发布时间<small>{{ $blog->created_at }}</small></p>

<hr>

<p> {{ $blog->content }} </p>
</div>
<a href="{{ route('blog.edit', $blog->id) }}" class="btn btn-info">编辑文章</a>
</div>
</div>
</div>

@endsection
