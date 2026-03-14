<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Comment;
use Illuminate\Http\Request;
use Validator;

class CommentController extends Controller
{
    public function store(Request $request, $blogId)
    {
        $blog = Blog::find($blogId);

        if (! $blog) {
            return response()->json(['error' => 'Blog not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'contenido' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $comment = Comment::create([
            'contenido' => $request->contenido,
            'user_id' => auth()->id(),
            'blog_id' => $blogId,
        ]);

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment->load('user'),
        ], 201);
    }
}
