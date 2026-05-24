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
    private static ?ObjectType $messageType = null;

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
                'user' => [
                    'type' => self::user(),
                    'args' => [
                        'id' => Type::nonNull(Type::int()),
                    ],
                    'resolve' => function ($root, array $args) {
                        $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$args['id']]);
                        $row = $stmt->fetch();
                        return $row ?: null;
                    },
                ],
                'searchPosts' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(self::post()))),
                    'args' => [
                        'keyword' => Type::nonNull(Type::string()),
                    ],
                    'resolve' => function ($root, array $args) {
                        $kw = $args['keyword'];
                        // VULN: sqli — keyword is concatenated straight into the SQL
                        $sql = "SELECT * FROM posts WHERE status = 'published' "
                             . "AND (title LIKE '%$kw%' OR body LIKE '%$kw%') "
                             . "ORDER BY created_at DESC";
                        return Db::pdo()->query($sql)->fetchAll();
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
                        // VULN: idor — email is returned to anyone who knows the user id
                        'email' => [
                            'type' => Type::string(),
                            'resolve' => fn($u) => $u['email'],
                        ],
                        // VULN: idor — drafts are private but returned to anyone
                        'drafts' => [
                            'type' => Type::nonNull(Type::listOf(Type::nonNull(self::post()))),
                            'resolve' => function ($user) {
                                $stmt = Db::pdo()->prepare(
                                    "SELECT * FROM posts WHERE author_id = ? AND status = 'draft' ORDER BY created_at DESC"
                                );
                                $stmt->execute([$user['id']]);
                                return $stmt->fetchAll();
                            },
                        ],
                        // VULN: idor — DMs to/from this user are returned without
                        // checking that the requester is involved in the conversation
                        'messages' => [
                            'type' => Type::nonNull(Type::listOf(Type::nonNull(self::message()))),
                            'resolve' => function ($user) {
                                $stmt = Db::pdo()->prepare(
                                    "SELECT * FROM messages WHERE sender_id = ? OR recipient_id = ? ORDER BY created_at DESC"
                                );
                                $stmt->execute([$user['id'], $user['id']]);
                                return $stmt->fetchAll();
                            },
                        ],
                    ];
                },
            ]);
        }
        return self::$userType;
    }

    private static function message(): ObjectType
    {
        if (self::$messageType === null) {
            self::$messageType = new ObjectType([
                'name' => 'Message',
                'fields' => function () {
                    return [
                        'id' => Type::nonNull(Type::int()),
                        'body' => Type::nonNull(Type::string()),
                        'createdAt' => [
                            'type' => Type::nonNull(Type::string()),
                            'resolve' => fn($m) => $m['created_at'],
                        ],
                        'sender' => [
                            'type' => Type::nonNull(self::user()),
                            'resolve' => function ($message) {
                                $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id = ?");
                                $stmt->execute([$message['sender_id']]);
                                return $stmt->fetch();
                            },
                        ],
                        'recipient' => [
                            'type' => Type::nonNull(self::user()),
                            'resolve' => function ($message) {
                                $stmt = Db::pdo()->prepare("SELECT * FROM users WHERE id = ?");
                                $stmt->execute([$message['recipient_id']]);
                                return $stmt->fetch();
                            },
                        ],
                    ];
                },
            ]);
        }
        return self::$messageType;
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
