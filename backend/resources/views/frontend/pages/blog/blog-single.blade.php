@extends('frontend.frontend-page-master')

@section('site-title')
    {{ $blog_post->title }}
@endsection

@section('page-title')
    {{ ucfirst('blog') }}
@endsection

@section('inner-title')
    {{ $blog_post->title }}
@endsection

@section('page-meta-data')
    <title>{{ $blog_post->title }}</title>
    {!! render_page_meta_data($blog_post) !!}
@endsection

@section('content')
    <section class="fm-hero py-5">
        <div class="container">
            <div class="hero-shell">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="hero-badge">
                            <i class="las la-pen-nib"></i>
                            {{ __('Blog and marketplace guidance') }}
                        </span>
                        <h1 class="hero-title">{{ $blog_post->title }}</h1>
                        <p class="hero-subtitle mb-0">
                            {{ __('Published through the same live Laravel content system used by the FUN MOMENT website.') }}
                        </p>
                    </div>
                    <div class="col-lg-4">
                        <div class="fm-glass-card p-4">
                            <div class="stat-card">
                                <div class="label">{{ __('Published') }}</div>
                                <div class="value" style="font-size:20px">{{ optional($blog_post->created_at)->toFormattedDateString() }}</div>
                            </div>
                            <div class="mt-3 stat-card">
                                <div class="label">{{ __('Category') }}</div>
                                <div class="value" style="font-size:20px">{{ optional($blog_post->category)->name }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-fm-section pt-0">
        <div class="container">
            <div class="fm-glass-card p-4 p-lg-5">
                <div class="single-blog-details">
                    <div class="thumb mb-4">
                        {!! render_image_markup_by_attachment_id($blog_post->image, '', 'large') !!}
                    </div>
                    <ul class="tags">
                        <li class="list">
                            <a href="javascript:void(0)"> <i class="las la-clock"></i> {{ optional($blog_post->created_at)->diffForHumans() }} </a>
                        </li>
                        <li class="list">
                            <a href="{{ route('frontend.blog.category', optional($blog_post->category)->slug) }}"> <i class="las la-tag"></i> {{ optional($blog_post->category)->name }} </a>
                        </li>
                    </ul>
                    <div class="details-para">{!! $blog_post->blog_content !!}</div>
                    @if(!empty($blog_post->excerpt))
                        <blockquote class="mt-4">
                            <div class="content">
                                <h3 class="blackquote-title">{{ $blog_post->excerpt }}</h3>
                            </div>
                        </blockquote>
                    @endif
                </div>

                <div class="details-tag-area padding-top-10">
                    <div class="row align-items-center">
                        <div class="col-lg-6 margin-top-30">
                            <div class="social-share">
                                <h4 class="share-tiitle">{{ get_static_option('blog_share_title') ?? __('Share:') }} </h4>
                                <ul>
                                    {!! single_post_share(route('frontend.blog.single',['id'=>$blog_post->id, 'slug'=> $blog_post->slug]), $blog_post->title, $blog_post->image) !!}
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-6 margin-top-30">
                            <div class="tag-list">
                                <h4 class="tag-tiitle">{{ get_static_option('blog_tag_title') ?? __('Tags:') }} </h4>
                                <ul>
                                    @foreach ($tags as $tag)
                                        <li>
                                            @if ($tag->tag_name && json_decode($tag->tag_name))
                                                @foreach (json_decode($tag->tag_name) as $tag_name)
                                                    @php
                                                        $slug = preg_replace('/[^a-zA-Z0-9]/', '-', $tag_name);
                                                        $slug = preg_replace('/-+/', '-', $slug);
                                                        $slug = trim($slug, '-');
                                                        $slug = strtolower($slug);
                                                    @endphp
                                                    @if ($tag_name)
                                                        <a href="{{ route('frontend.blog.tags', $slug) }}">{{ $tag_name }}</a>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="related-blog-area padding-top-100">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="section-title-two">
                                <h3 class="title">{{ get_static_option('related_blog_title') ?? __('Related Blog') }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="row padding-top-20">
                        @if(!empty($related_blog))
                            @foreach($related_blog as $blog)
                                <div class="col-lg-4 col-md-6 margin-top-30">
                                    <article class="single-blog no-margin wow fadeInUp" data-wow-delay=".2s">
                                        <a href="{{ route('frontend.blog.single', $blog->slug) }}" class="blog-thumb service-bg-thumb-format" {!! render_background_image_markup_by_attachment_id($blog->image) !!}></a>
                                        <div class="blog-contents">
                                            <ul class="tags">
                                                <li>
                                                    <a href="javascript:void(0)"> <i class="las la-clock"></i>{{ optional($blog->created_at)->diffForHumans() }} </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('frontend.blog.category', optional($blog->category)->slug) }}"> <i class="las la-tag"></i>{{ optional($blog->category)->name }} </a>
                                                </li>
                                            </ul>
                                            <h5 class="common-title">
                                                <a href="{{ route('frontend.blog.single', $blog->slug) }}">{{ $blog->title }}</a>
                                            </h5>
                                            <p class="common-para">{!! Str::words(strip_tags($blog->blog_content), 20) !!}</p>
                                        </div>
                                    </article>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="comment-area padding-top-100">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="section-title-two">
                                <h3 class="title">{{ get_static_option('blog_comment_title') ?? __('Post Your Comments') }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12 padding-top-20">
                            @if(Auth::guard('web')->check())
                                <form action="" class="blog_comment_form" method="post">
                                    @csrf
                                    <input type="hidden" value="{{ $blog_post->id }}" name="blog_id" id="blog_id">

                                    <div class="details-comment-content">
                                        <div class="comments-flex-item">
                                            <div class="single-commetns">
                                                <label class="comment-label">{{ get_static_option('blog_comment_name_title') ?? __('Your Name*') }} </label>
                                                <input type="text" class="form--control" name="name" id="name" value="{{ Auth::guard('web')->user()->name ?? '' }}" placeholder="{{ __('Type Name') }}">
                                            </div>
                                            <div class="single-commetns">
                                                <label class="comment-label"> {{ get_static_option('blog_comment_email_title') ?? __('Email Address*') }} </label>
                                                <input type="text" class="form--control" name="email" id="email" value="{{ Auth::guard('web')->user()->email ?? '' }}" placeholder="{{ __('Type Email') }}">
                                            </div>
                                        </div>
                                        <div class="single-commetns">
                                            <label class="comment-label"> {{ get_static_option('blog_comment_message_title') ?? __('Comments*') }} </label>
                                            <textarea name="message" id="message" class="form--control form--message" placeholder="{{ __('Post Comments') }}"></textarea>
                                        </div>
                                        <button type="submit">{{ get_static_option('blog_comment_button_title') ?? __('Post Comments') }}</button>
                                    </div>
                                </form>
                            @else
                                @if(empty(get_static_option('disable_user_otp_verify')))
                                    <a class="cmn-btn btn-outline-1" href="{{ route('user.login') . '?return=' . request()->path() }}">{{ __('Sign in for comment') }}</a>
                                @else
                                    <a class="btn btn-sm btn-success text-white" data-bs-toggle="modal" data-bs-target="#commentModal">{{ __('Sign in for comment') }}</a>
                                @endif
                            @endif

                            @foreach($blog_post->comments as $comment)
                                <div class="comment-show-contents padding-top-30">
                                    <div class="about-seller-flex-content style-03">
                                        <div class="about-seller-thumb">
                                            <a href="javascript:void(0)">
                                                {!! render_image_markup_by_attachment_id(optional($comment->user)->image, '', 'thumb') !!}
                                            </a>
                                        </div>
                                        <div class="about-seller-content">
                                            <h5 class="title"><a href="javascript:void(0)">{{ $comment->name }}</a></h5>
                                            <p class="about-review-para">{{ $comment->message }}</p>
                                            <span class="review-date">{{ optional($comment->created_at)->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="display:block">
                    <h5 class="modal-title" id="commentModalLabel">{{ __('Sign In For Comment') }}</h5>
                    <p class="login_error_msg text-danger"></p>
                </div>
                <div class="modal-body">
                    <form action="{{ route('frontend.blog.comment.signin') }}" method="post">
                        <div class="form-group">
                            <label for="username">{{ __('User Name') }}</label>
                            <input type="text" class="form-control" name="username" id="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">{{ __('Password') }}</label>
                            <input type="password" class="form-control" name="password" id="password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="button" class="btn btn-primary" id="login_form_for_comment">{{ __('Sign In') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('assets/frontend/js/rating.js') }}"></script>
    <script>
        (function($){
            "use strict";

            $(document).ready(function(){
                $(document).on('submit','.blog_comment_form',function(e){
                    e.preventDefault();
                    let blog_id = $('#blog_id').val();
                    let name = $('#name').val();
                    let email = $('#email').val();
                    let message = $('#message').val();

                    $.ajax({
                        url:"{{ route('frontend.blog.comment') }}",
                        method:"post",
                        data:{
                            blog_id:blog_id,
                            name:name,
                            email:email,
                            message:message,
                        },
                        success:function(res){
                            if (res.status == 'success') {
                                toastr.success('Success!! Thanks For Comments---');
                            }
                            $('.blog_comment_form')[0].reset();
                        }
                    });
                });

                $(document).on('click','#login_form_for_comment',function (e){
                    e.preventDefault();
                    $.ajax({
                        url: "{{route('frontend.blog.comment.signin')}}",
                        type: "POST",
                        data: {
                            username : $('#username').val(),
                            password : $('#password').val(),
                        },
                        success:function (data){
                            if (data.status == 'success'){
                                location.reload();
                            }
                            if (data.status == 'error'){
                                $('.login_error_msg').text(data.msg);
                            }
                        }
                    });
                });
            });
        })(jQuery);
    </script>
@endsection
