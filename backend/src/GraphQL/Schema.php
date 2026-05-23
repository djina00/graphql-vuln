<?php
declare(strict_types=1);

namespace App\GraphQL;

use App\Db;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema as GqlSchema;

class Schema
{
    private static ?ObjectType $userType = null;
    private static ?ObjectType $postType = null;
    private static ?ObjectType $commentType = null;

    public static function build(): GqlSchema
    {
        return new GqlSchema([
            'query' => self::queryType(),
        ]);
    }

    private static function queryType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Query',
            'fields' => [
                'posts' => [
                    'type' => Type::listOf(Type::nonNull(self::post())),
                    'resolve' => function () {
                        $stmt = Db::pdo()->query(
                            "SELECT * FROM posts WHERE status = 'published' ORDER BY created_at DESC"
                        );
                        return $stmt->fetchAll();
                    },
                ],
                'post' => [
                    'type' => self::post(),
                    'args' => [
                        'id' => Type::nonNull(Type::int()),
                    ],
                    'resolve' => function ($root, array $args) {
                        $stmt = Db::pdo()->prepare("SELECT * FROM posts WHERE id = ?");
                        $stmt->execute([$args['id']]);
                        $row = $stmt->fetch();
                        return $row ?: null;
                    },
                ],
            ],
        ]);
    }

    private static function user(): ObjectType
    {
        if (self::$userType === null) {
            self::$userType = new ObjectType([
                'name' => 'User',
                'fields' => function () {
                    return [
                        'id' => Type::nonNull(Type::int()),
                        'displayName' => [
                            'type' => Type::nonNull(Type::string()),
                            'resolve' => fn($u) => $u['display_name'],
                        ],
                        'bio' => Type::string(),
                    ];
                },
            ]);
        }
        return self::$userType;
    }

    private static function post(): ObjectType
    {
        if (self::$postType === null) {
            self::$postType = new ObjectType([
                'name' => 'Post',
                'fields' => function () {
                    return [
                        'id' => Type::nonNull(Type::int()),
                        'title' => Type::nonNull(Type::string()),
                        'body' => Type::nonNull(Type::string()),
                        'status' => Type::nonNull(Type::string()),
                        'createdAt' => [
                            'type' => Type::nonNull(Type::string()),
                            'resolve' => fn($p) => $p['created_at'],
                        ],
                        'author' => [
                            'type' => Type::nonNull(self::user()),
                            'resolve' => function ($post) {
                                $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id = ?");
                                $stmt->execute([$post['author_id']]);
                                return $stmt->fetch();
                            },
                        ],
                        'comments' => [
                            'type' => Type::nonNull(Type::listOf(Type::nonNull(self::comment()))),
                            'resolve' => function ($post) {
                                $stmt = Db::pdo()->prepare(
                                    "SELECT * FROM comments WHERE post_id = ? ORDER BY created_at ASC"
                                );
                                $stmt->execute([$post['id']]);
                                return $stmt->fetchAll();
                            },
                        ],
                    ];
                },
            ]);
        }
        return self::$postType;
    }

    private static function comment(): ObjectType
    {
        if (self::$commentType === null) {
            self::$commentType = new ObjectType([
                'name' => 'Comment',
                'fields' => function () {
                    return [
                        'id' => Type::nonNull(Type::int()),
                        'body' => Type::nonNull(Type::string()),
                        'createdAt' => [
                            'type' => Type::nonNull(Type::string()),
                            'resolve' => fn($c) => $c['created_at'],
                        ],
                        'author' => [
                            'type' => Type::nonNull(self::user()),
                            'resolve' => function ($comment) {
                                $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id = ?");
                                $stmt->execute([$comment['author_id']]);
                                return $stmt->fetch();
                            },
                        ],
                    ];
                },
            ]);
        }
        return self::$commentType;
    }
}
