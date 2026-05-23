-- Test users. Passwords: alice123, bob123, carol123
INSERT INTO users (email, password_hash, display_name, bio) VALUES
('alice@blog.com', '$2y$10$i2U3qFJnkwreVK/Do5.vi.PftXrFqCD2cWv0zLEUrEc9yuSydBj16', 'Alice', 'Writes about books and coffee.'),
('bob@blog.com',   '$2y$10$NItZ8EZoDfO5QPCX0zbLM.4jscXl/LiPzh91OEV89l1hK/XPMYkoK', 'Bob',   'Backend dev, occasional rant.'),
('carol@blog.com', '$2y$10$G6BK.qzF0j3Uzg1XZ60rDOyRL1A.BgZPtAPIGviH8i97.jzp.IJ3a', 'Carol', 'Photographer and traveler.');

INSERT INTO posts (author_id, title, body, status) VALUES
(1, 'On rereading old books', 'There is something quiet about going back to a book you finished ten years ago. The plot is the same but you are not.', 'published'),
(1, 'Draft: notes on a memoir', 'Still figuring out where this is going. Probably going to cut the second half.', 'draft'),
(2, 'Why I stopped using ORM tricks', 'Every time I get clever with an ORM I end up rewriting the query in raw SQL six months later.', 'published'),
(2, 'Draft: side project postmortem', 'Need to be honest about what went wrong here before I publish.', 'draft'),
(3, 'A weekend in Kotor', 'Old town, narrow streets, and that one cat sleeping on the cathedral steps for three hours.', 'published'),
(3, 'Camera bag, finally sorted', 'After two years of carrying things I never used, I cut the kit in half. Here is what stayed.', 'published');

INSERT INTO comments (post_id, author_id, body) VALUES
(1, 2, 'I do this every winter with the same three books.'),
(1, 3, 'Which one did you reread this time?'),
(3, 1, 'Same. The clever query is a smell.'),
(5, 2, 'Kotor is high on my list. Was it crowded?'),
(6, 1, 'Curious which lens you dropped.');

-- Private messages between users
INSERT INTO messages (sender_id, recipient_id, body) VALUES
(1, 2, 'Hey Bob, are you still up for reviewing my draft this week?'),
(2, 1, 'Yes, send it over. Thursday evening works.'),
(3, 1, 'Alice, do you have the email for the editor we talked about?'),
(1, 3, 'I will dig it up tonight and forward it.');
