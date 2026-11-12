-- ============================================================
-- CodeLearn · database schema and sample data
-- MySQL / MariaDB (XAMPP)
--
-- Setup:
--   1. Start Apache and MySQL in the XAMPP control panel.
--   2. Open http://localhost/phpmyadmin
--   3. Import this file (Import tab) or paste it into the SQL tab.
--   4. Copy this project into htdocs and open http://localhost/inn/
--
-- Re-running this file resets the database and its sample rows.
-- ============================================================

CREATE DATABASE IF NOT EXISTS codelearn
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE codelearn;

DROP TABLE IF EXISTS progress;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS learners;
DROP TABLE IF EXISTS languages;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS media;

CREATE TABLE languages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60) NOT NULL UNIQUE,
    paradigm    VARCHAR(80) DEFAULT NULL,
    difficulty  ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL DEFAULT 'Beginner',
    description VARCHAR(600) DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE learners (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name        VARCHAR(100) NOT NULL,
    email            VARCHAR(150) NOT NULL UNIQUE,
    country          VARCHAR(60) DEFAULT NULL,
    experience_level ENUM('Beginner', 'Some experience', 'Professional') NOT NULL DEFAULT 'Beginner',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE lessons (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    language_id      INT UNSIGNED NOT NULL,
    title            VARCHAR(150) NOT NULL,
    summary          VARCHAR(255) DEFAULT NULL,
    content          TEXT NOT NULL,
    code_example     TEXT DEFAULT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lessons_language
        FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE CASCADE,
    INDEX idx_lessons_language (language_id)
) ENGINE = InnoDB;

CREATE TABLE progress (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    learner_id   INT UNSIGNED NOT NULL,
    lesson_id    INT UNSIGNED NOT NULL,
    status       ENUM('In progress', 'Completed') NOT NULL DEFAULT 'In progress',
    score        TINYINT UNSIGNED DEFAULT NULL,
    notes        VARCHAR(255) DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uniq_progress_learner_lesson UNIQUE (learner_id, lesson_id),
    CONSTRAINT chk_progress_score CHECK (score BETWEEN 0 AND 100),
    CONSTRAINT fk_progress_learner
        FOREIGN KEY (learner_id) REFERENCES learners (id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_lesson
        FOREIGN KEY (lesson_id) REFERENCES lessons (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE contact_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    subject    VARCHAR(150) NOT NULL,
    message    TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_created (created_at)
) ENGINE = InnoDB;

CREATE TABLE media (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(120) NOT NULL,
    stored_name   VARCHAR(80) NOT NULL,
    original_name VARCHAR(200) DEFAULT NULL,
    mime_type     VARCHAR(40) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL,
    source        ENUM('upload', 'url') NOT NULL DEFAULT 'upload',
    source_url    VARCHAR(500) DEFAULT NULL,
    page_key      VARCHAR(40) DEFAULT NULL,
    uploaded_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uniq_media_stored UNIQUE (stored_name),
    CONSTRAINT uniq_media_page UNIQUE (page_key),
    INDEX idx_media_uploaded (uploaded_at)
) ENGINE = InnoDB;

-- ============================================================
-- Languages
-- ============================================================

INSERT INTO languages (name, paradigm, difficulty, description) VALUES
('HTML & CSS', 'Markup & styling', 'Beginner', 'The building blocks of the web: semantic markup with HTML and visual design with CSS.'),
('JavaScript', 'Web / frontend', 'Beginner', 'The language of the browser, also used across the full stack with Node.js.'),
('TypeScript', 'Typed JavaScript', 'Intermediate', 'A superset of JavaScript that adds static types and scales to large codebases.'),
('Python', 'General-purpose', 'Beginner', 'Readable, versatile and dominant in automation, data science and AI.'),
('Java', 'Object-oriented', 'Intermediate', 'A compiled, class-based language behind enterprise systems and Android apps.'),
('C', 'Systems', 'Intermediate', 'The compact language that powers operating systems, compilers and embedded devices.'),
('C++', 'Systems / OOP', 'Advanced', 'High-performance C with classes, templates and manual memory control.'),
('C#', 'Object-oriented', 'Intermediate', 'A modern .NET language for desktop, web, games and cloud services.'),
('Go', 'Systems / concurrency', 'Intermediate', 'A simple, fast language from Google with first-class concurrency for scalable services.'),
('Rust', 'Systems', 'Advanced', 'Memory-safe systems programming without a garbage collector.'),
('PHP', 'Web backend', 'Beginner', 'The server-side language that powers a huge share of the web, including this app.'),
('Ruby', 'Object-oriented', 'Beginner', 'An elegant, developer-friendly language known for the Rails framework.'),
('Swift', 'Apple platforms', 'Intermediate', 'The modern language for iOS, macOS, watchOS and tvOS apps.'),
('Kotlin', 'Android / JVM', 'Intermediate', 'A concise, null-safe JVM language that has become the default choice for Android.'),
('Dart', 'Cross-platform apps', 'Beginner', 'The language behind Flutter, for mobile and web interfaces built from one codebase.'),
('SQL', 'Data / querying', 'Beginner', 'The standard language for querying and shaping relational data.'),
('R', 'Data science', 'Intermediate', 'A statistics-first language for analysis, visualization and research.'),
('Lua', 'Scripting / games', 'Beginner', 'A tiny embeddable language popular in game engines such as Roblox and LOVE.'),
('Scala', 'Functional / OOP', 'Advanced', 'Runs on the JVM and blends object-oriented and functional programming.'),
('Haskell', 'Functional', 'Advanced', 'A purely functional language that treats computation as mathematics.'),
('Elixir', 'Functional / concurrent', 'Advanced', 'Built on the Erlang VM for fault-tolerant, massively concurrent systems.'),
('Perl', 'Scripting', 'Intermediate', 'A powerful text-processing language with deep roots in system administration.'),
('MATLAB', 'Scientific computing', 'Intermediate', 'A numerical computing environment widely used in engineering and research.'),
('Shell / Bash', 'Scripting / automation', 'Beginner', 'Automate the command line, files and system tasks on Unix-like machines.'),
('Assembly', 'Low-level', 'Advanced', 'Human-readable machine code that talks directly to the processor.');

-- ============================================================
-- Sample learners
-- ============================================================

INSERT INTO learners (full_name, email, country, experience_level, created_at) VALUES
('Amara Okafor', 'amara.okafor@example.com', 'Nigeria', 'Professional', NOW() - INTERVAL 60 DAY),
('Liam Chen', 'liam.chen@example.com', 'Singapore', 'Some experience', NOW() - INTERVAL 45 DAY),
('Sofia Reyes', 'sofia.reyes@example.com', 'Mexico', 'Beginner', NOW() - INTERVAL 30 DAY),
('Noah Fischer', 'noah.fischer@example.com', 'Germany', 'Some experience', NOW() - INTERVAL 18 DAY);

-- ============================================================
-- Lessons
-- ============================================================

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'HTML & CSS'),
 'Build your first web page',
 'Structure with semantic HTML, then style it with CSS.',
 'A web page is a tree of elements. Tags such as <header>, <main> and <footer> describe what the content is, while CSS decides how it looks. Save the example as index.html and open it in any browser.',
 '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My first page</title>
  <style>
    body { font-family: system-ui; margin: 40px; color: #1f2937; }
    h1 { color: #4f46e5; }
  </style>
</head>
<body>
  <h1>Hello, web!</h1>
  <p>This page is built with HTML and styled with CSS.</p>
</body>
</html>',
 30, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'HTML & CSS'),
 'Flexbox in practice',
 'Lay out navigation and cards with display: flex, gap and alignment.',
 'Flexbox distributes space along one axis. Set display: flex on a container, then align items and control spacing with gap. It is the fastest way to build toolbars, card rows and page headers.',
 '.nav {
  display: flex;
  align-items: center;
  gap: 12px;
}

.nav a {
  padding: 8px 14px;
  border-radius: 999px;
  text-decoration: none;
}',
 35, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'JavaScript'),
 'Your first script',
 'Run JavaScript in the browser console and work with variables.',
 'JavaScript is the language of the browser. Open DevTools, switch to the Console tab and paste a few lines. Variables declared with const cannot be reassigned; let is for values that change.',
 'const learner = "Ada";
let completed = 0;

completed += 1;

console.log(`${learner} completed ${completed} lesson.`);',
 25, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'JavaScript'),
 'Functions and scope',
 'Reuse logic with functions and understand where variables live.',
 'A function packages logic behind a name. Parameters are local to the function body, while variables declared outside are visible inside. Arrow functions offer a compact syntax for short callbacks.',
 'const lessons = ["HTML", "CSS", "JavaScript"];

const done = lessons.map((lesson) => `${lesson} done`);

console.log(done.join(", "));',
 30, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'JavaScript'),
 'Meet the DOM',
 'Select elements, react to clicks and update the page with JavaScript.',
 'The Document Object Model represents the page as objects. querySelector finds an element and addEventListener reacts to user actions. The handler below updates the page without a reload.',
 '<button id="start">Start lesson</button>
<p id="status">Not started</p>

<script>
  const status = document.querySelector("#status");

  document.querySelector("#start").addEventListener("click", () => {
    status.textContent = "Lesson started!";
  });
</script>',
 40, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'TypeScript'),
 'Types that catch bugs',
 'Add static types to JavaScript and catch mistakes before running code.',
 'TypeScript is JavaScript with types. Interfaces describe the shape of objects, and the compiler flags mistakes while you type. Browsers do not run TypeScript directly, so it is compiled to plain JavaScript.',
 'interface Learner {
  name: string;
  completed: number;
}

function progress(learner: Learner): string {
  return `${learner.name} finished ${learner.completed} lessons`;
}

console.log(progress({ name: "Ada", completed: 6 }));',
 35, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Python'),
 'Hello, Python',
 'Write and run your first Python program in seconds.',
 'Python reads almost like English. Save the snippet as hello.py and run it with python hello.py. Strings can be combined with f-strings, which insert expressions inside braces.',
 'language = "Python"
print(f"Hello, {language}!")

for step in range(1, 4):
    print(f"Step {step} complete")',
 25, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Python'),
 'Loops and decisions',
 'Control the flow of a program with if statements and loops.',
 'Conditionals choose a path; loops repeat work. Python uses indentation instead of braces, so the structure of the code is visible at a glance. The snippet classifies scores stored in a list.',
 'scores = [92, 58, 77, 100, 64]

for score in scores:
    if score >= 90:
        print(score, "excellent")
    elif score >= 60:
        print(score, "passed")
    else:
        print(score, "needs practice")',
 35, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Java'),
 'Classes and objects',
 'Model real-world data with classes, fields and methods.',
 'Java programs are built from classes. A class defines fields and methods, and objects are instances created with new. The main method is the entry point of every application.',
 'public class Lesson {
    private final String title;
    private boolean completed;

    public Lesson(String title) {
        this.title = title;
    }

    public void complete() {
        completed = true;
    }

    public String status() {
        return completed ? title + ": done" : title + ": pending";
    }

    public static void main(String[] args) {
        Lesson lesson = new Lesson("Classes and objects");
        lesson.complete();
        System.out.println(lesson.status());
    }
}',
 40, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Java'),
 'Working with collections',
 'Store and iterate groups of values with lists and maps.',
 'The collections framework replaces raw arrays with flexible data structures. ArrayList keeps insertion order, while HashMap stores key-value pairs for fast lookups.',
 'import java.util.ArrayList;
import java.util.List;

List<String> languages = new ArrayList<>();
languages.add("Java");
languages.add("Kotlin");

for (String language : languages) {
    System.out.println(language);
}',
 35, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'C'),
 'Pointers without fear',
 'Understand addresses, dereferencing and why pointers matter.',
 'A pointer stores a memory address. The & operator takes an address, and * reads or writes the value at that address. Pointers make it possible to pass large data around without copying it.',
 '#include <stdio.h>

int main(void) {
    int score = 42;
    int *pointer = &score;

    printf("value: %d\\n", *pointer);
    *pointer = 99;
    printf("updated: %d\\n", score);

    return 0;
}',
 45, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'C++'),
 'Classes in C++',
 'Combine data and behaviour with classes and constructors.',
 'C++ adds object-oriented features to C. A class groups data with the functions that operate on it, and a constructor initialises new objects. Header files usually declare a class while source files define its methods.',
 '#include <iostream>
#include <string>

class Lesson {
public:
    explicit Lesson(std::string title) : title_(title) {}

    void complete() { completed_ = true; }

    void print() const {
        std::cout << title_ << (completed_ ? " done" : " pending") << "\\n";
    }

private:
    std::string title_;
    bool completed_ = false;
};

int main() {
    Lesson lesson("Classes in C++");
    lesson.complete();
    lesson.print();
    return 0;
}',
 45, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'C#'),
 'Your first .NET program',
 'Create a console app with classes, properties and modern C# syntax.',
 'C# runs on the cross-platform .NET runtime. Top-level statements let a small program start without a Main method, while properties make fields easy to read and write safely.',
 'using System;

var lesson = new Lesson("Your first .NET program");
lesson.Complete();
Console.WriteLine(lesson.Status);

class Lesson
{
    public Lesson(string title) => Title = title;

    public string Title { get; }
    public bool Completed { get; private set; }

    public void Complete() => Completed = true;

    public string Status => $"{Title}: {(Completed ? "done" : "pending")}";
}',
 40, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Go'),
 'Goroutines and channels',
 'Run work concurrently and communicate safely with channels.',
 'A goroutine is a lightweight thread started with the go keyword. Channels pass values between goroutines so they can coordinate without shared memory. The program below starts two workers and receives each result as it arrives.',
 'package main

import "fmt"

func announce(name string, done chan<- string) {
    done <- name + " is ready"
}

func main() {
    done := make(chan string)
    go announce("lesson 1", done)
    go announce("lesson 2", done)
    fmt.Println(<-done)
    fmt.Println(<-done)
}',
 40, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Rust'),
 'Ownership and borrowing',
 'Learn why Rust needs no garbage collector to stay memory safe.',
 'Every value in Rust has a single owner. When the owner goes out of scope the value is dropped, which frees memory without a garbage collector. References borrow a value without taking ownership.',
 'fn main() {
    let title = String::from("Ownership and borrowing");
    let length = measure(&title);

    println!("{title} has {length} characters");
}

fn measure(text: &String) -> usize {
    text.chars().count()
}',
 50, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'PHP'),
 'Handling HTML forms',
 'Read submitted form data safely and show it back to the user.',
 'PHP receives form submissions in the $_POST array. Always validate on the server, then escape output with htmlspecialchars so user input cannot inject HTML. The example greets the value submitted by a small form.',
 '<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    echo "Hello, " . htmlspecialchars($name, ENT_QUOTES, "UTF-8");
}
?>
<form method="post">
    <input type="text" name="name" required>
    <button type="submit">Say hello</button>
</form>',
 30, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'PHP'),
 'PHP meets MySQL',
 'Connect to MySQL with PDO and run prepared statements.',
 'PDO gives PHP a consistent database API. Prepared statements keep SQL and user data separate, which prevents injection. This platform stores every lesson with the same pattern.',
 '<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=codelearn;charset=utf8mb4", "root", "");

$statement = $pdo->prepare(
    "INSERT INTO languages (name, difficulty) VALUES (?, ?)"
);
$statement->execute(["Zig", "Intermediate"]);

echo "Saved with id " . $pdo->lastInsertId();',
 40, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Ruby'),
 'Blocks and iterators',
 'Iterate collections elegantly with blocks and enumerable methods.',
 'Ruby methods often take a block, which is a chunk of code written between do/end or braces. Enumerable methods such as each, map and select make collection processing concise and readable.',
 'languages = ["Ruby", "Python", "Rust"]

languages.each do |language|
  puts "Learning #{language}"
end

short = languages.select { |language| language.length < 5 }
puts short.join(", ")',
 30, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Swift'),
 'Build a SwiftUI view',
 'Compose a simple interface from SwiftUI stacks and text views.',
 'SwiftUI describes interfaces as a function of state. The body property lists views in a vertical stack, and changes to state update the screen automatically. Build the example in Xcode with a SwiftUI app template.',
 'import SwiftUI

struct LessonView: View {
    @State private var completed = false

    var body: some View {
        VStack(spacing: 16) {
            Text("SwiftUI basics")
                .font(.title2)

            Button(completed ? "Completed" : "Mark complete") {
                completed = true
            }
            .buttonStyle(.borderedProminent)
        }
        .padding()
    }
}',
 45, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Kotlin'),
 'Null safety explained',
 'Stop null pointer errors with nullable types and safe calls.',
 'Kotlin separates nullable and non-nullable types at compile time. A question mark marks a value that may be null, and the safe call operator ?. returns null instead of crashing. The Elvis operator ?: provides a fallback.',
 'data class Learner(val name: String, val mentor: String?)

fun describe(learner: Learner): String {
    val mentor = learner.mentor ?: "no mentor yet"
    return "${learner.name} has $mentor"
}

fun main() {
    println(describe(Learner("Ada", null)))
    println(describe(Learner("Linus", "Grace")))
}',
 30, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Dart'),
 'Widgets 101',
 'Build a Flutter screen from a tree of widgets.',
 'In Flutter everything is a widget. Widgets are combined into a tree, and the framework rebuilds only what changed. The example shows a stateless screen with a title and a centred message.',
 'import "package:flutter/material.dart";

void main() => runApp(const CodeLearnApp());

class CodeLearnApp extends StatelessWidget {
  const CodeLearnApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: Scaffold(
        appBar: AppBar(title: const Text("CodeLearn")),
        body: const Center(child: Text("Pick a language to begin")),
      ),
    );
  }
}',
 35, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'SQL'),
 'SELECT basics',
 'Read rows, choose columns and filter results with WHERE.',
 'SELECT returns rows from a table. List the columns you need, filter with WHERE and sort with ORDER BY. These sample queries run against the tables used by this platform.',
 'SELECT title, duration_minutes
FROM lessons
WHERE duration_minutes <= 30
ORDER BY duration_minutes ASC;

SELECT language_id, COUNT(*) AS lesson_count
FROM lessons
GROUP BY language_id;',
 25, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'SQL'),
 'Joining tables',
 'Combine related tables with inner joins and aggregate the result.',
 'A join links rows in one table to matching rows in another. INNER JOIN keeps only matches; LEFT JOIN keeps unmatched rows from the first table. This query lists each lesson with the language it belongs to.',
 'SELECT l.title, lg.name AS language, p.status
FROM progress p
JOIN lessons l ON l.id = p.lesson_id
JOIN languages lg ON lg.id = l.language_id
WHERE p.status = ''Completed''
ORDER BY l.title;',
 35, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'R'),
 'Data frames',
 'Load, filter and summarise tabular data in R.',
 'A data frame is the table structure in R. The dplyr package provides verbs such as filter, select and summarise that read naturally and compose well.',
 'library(dplyr)

lessons <- data.frame(
  title = c("Python basics", "SQL joins", "Rust ownership"),
  minutes = c(25, 35, 50)
)

lessons %>%
  filter(minutes >= 30) %>%
  summarise(count = n(), average = mean(minutes))',
 35, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Lua'),
 'Tables and loops',
 'Use Lua tables for lists, maps and everything in between.',
 'Lua has a single container type: the table. Tables can act as arrays with numeric keys or as dictionaries with string keys. The length operator returns the size of an array-style table.',
 'local languages = {"Lua", "Python", "Rust"}

for index, language in ipairs(languages) do
  print(index, language)
end

local profile = {name = "Ada", level = "Beginner"}
print(profile.name, profile.level)',
 25, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Scala'),
 'Getting started with Scala',
 'Write concise functional code on the Java virtual machine.',
 'Scala combines object-oriented and functional programming on the JVM. Values declared with val cannot change, and collection methods such as map and filter transform data without mutation.',
 'object Main extends App {
  val languages = List("Scala", "Java", "Kotlin")

  val shortNames = languages
    .filter(_.length <= 4)
    .map(_.toUpperCase)

  println(shortNames.mkString(", "))
}',
 45, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Haskell'),
 'Pure functions and recursion',
 'Think in transforms and recursion with a purely functional language.',
 'Haskell functions are pure: the same input always produces the same output. Recursion replaces loops, and pattern matching makes base cases explicit. GHC runs the example as a standalone program.',
 'factorial :: Integer -> Integer
factorial 0 = 1
factorial n = n * factorial (n - 1)

main :: IO ()
main = print (map factorial [1 .. 6])',
 45, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Elixir'),
 'Pattern matching and processes',
 'Match shapes and spawn lightweight processes on the Erlang VM.',
 'In Elixir the equals sign is a match operator, not just assignment. Processes are cheap and isolated, and they communicate by sending messages. This example spawns a process that replies to a request.',
 'defmodule Worker do
  def start do
    spawn(fn ->
      receive do
        {:greet, from, name} -> send(from, "Hello, #{name}!")
      end
    end)
  end
end

worker = Worker.start()
send(worker, {:greet, self(), "Ada"})

receive do
  message -> IO.puts(message)
end',
 45, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Perl'),
 'Text processing with Perl',
 'Slice, match and transform text with Perl regular expressions.',
 'Perl was built for text manipulation. Regular expressions are part of the syntax, and functions such as split, grep and map make short work of logs and reports.',
 'use strict;
use warnings;

my $log = "INFO build ok\\nWARN cache miss\\nERROR deploy failed\\n";

my @errors = grep { /^ERROR/ } split /\\n/, $log;

print "Found " . scalar(@errors) . " error(s)\\n";
print join("\\n", @errors), "\\n";',
 35, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'MATLAB'),
 'Matrices and plotting',
 'Work with matrices and visualise results in MATLAB.',
 'MATLAB treats every value as a matrix. Operations apply to whole arrays, and plotting is a single function call. The example samples a sine wave and draws it.',
 'x = 0:0.1:2*pi;
y = sin(x);

plot(x, y, "LineWidth", 2)
title("Sine wave")
xlabel("Radians")
ylabel("Amplitude")
grid on',
 40, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Shell / Bash'),
 'Files, pipes and scripts',
 'Automate everyday tasks with the Unix command line.',
 'A shell script is a list of commands executed in order. Pipes connect the output of one command to the input of the next, which makes small tools combine into powerful workflows.',
 '#!/usr/bin/env bash
set -euo pipefail

mkdir -p lessons

for language in python javascript rust; do
  echo "# Notes for $language" > "lessons/$language.md"
done

wc -l lessons/*.md | tail -n 1',
 25, NOW() - INTERVAL 90 DAY);

INSERT INTO lessons (language_id, title, summary, content, code_example, duration_minutes, created_at) VALUES
((SELECT id FROM languages WHERE name = 'Assembly'),
 'Registers and instructions',
 'See how source code becomes instructions the CPU executes.',
 'Assembly is a human-readable form of machine code. Registers hold the values being worked on, and each instruction performs one small operation. The example is x86-64 Linux syntax and exits with status 42.',
 'section .text
global _start

_start:
    mov rax, 60      ; syscall: exit
    mov rdi, 42      ; status code
    syscall',
 55, NOW() - INTERVAL 90 DAY);

-- ============================================================
-- Sample progress (one row per learner and lesson pair)
-- ============================================================

SET @amara := (SELECT id FROM learners WHERE email = 'amara.okafor@example.com');
SET @liam  := (SELECT id FROM learners WHERE email = 'liam.chen@example.com');
SET @sofia := (SELECT id FROM learners WHERE email = 'sofia.reyes@example.com');
SET @noah  := (SELECT id FROM learners WHERE email = 'noah.fischer@example.com');

INSERT INTO progress (learner_id, lesson_id, status, score, notes, completed_at, updated_at) VALUES
(@amara, (SELECT id FROM lessons WHERE title = 'Build your first web page'), 'Completed', 98, 'First page published locally.', NOW() - INTERVAL 27 DAY, NOW() - INTERVAL 27 DAY),
(@amara, (SELECT id FROM lessons WHERE title = 'Hello, Python'), 'Completed', 95, 'Skipped the intro and ran the code.', NOW() - INTERVAL 24 DAY, NOW() - INTERVAL 24 DAY),
(@amara, (SELECT id FROM lessons WHERE title = 'Loops and decisions'), 'Completed', 88, 'Loops clicked after two attempts.', NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 20 DAY),
(@amara, (SELECT id FROM lessons WHERE title = 'SELECT basics'), 'Completed', 92, 'Comfortable filtering lessons by length.', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY),
(@amara, (SELECT id FROM lessons WHERE title = 'Joining tables'), 'In progress', 70, 'Halfway through the join examples.', NULL, NOW() - INTERVAL 2 DAY),

(@liam, (SELECT id FROM lessons WHERE title = 'Your first script'), 'Completed', 90, 'Console practice done.', NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 18 DAY),
(@liam, (SELECT id FROM lessons WHERE title = 'Functions and scope'), 'Completed', 85, 'Arrow functions are clear now.', NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 14 DAY),
(@liam, (SELECT id FROM lessons WHERE title = 'Types that catch bugs'), 'Completed', 78, 'Types caught a bug in a small script.', NOW() - INTERVAL 8 DAY, NOW() - INTERVAL 8 DAY),
(@liam, (SELECT id FROM lessons WHERE title = 'Meet the DOM'), 'In progress', NULL, 'Building a click counter next.', NULL, NOW() - INTERVAL 1 DAY),

(@sofia, (SELECT id FROM lessons WHERE title = 'Build your first web page'), 'Completed', 100, 'Built a full page from scratch.', NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 15 DAY),
(@sofia, (SELECT id FROM lessons WHERE title = 'Flexbox in practice'), 'In progress', NULL, 'Practising gap and alignment.', NULL, NOW() - INTERVAL 3 DAY),
(@sofia, (SELECT id FROM lessons WHERE title = 'Hello, Python'), 'In progress', NULL, 'Just started. Taking notes on indentation.', NULL, NOW() - INTERVAL 5 HOUR),

(@noah, (SELECT id FROM lessons WHERE title = 'Goroutines and channels'), 'In progress', NULL, 'Channels still confusing. Revisiting.', NULL, NOW() - INTERVAL 12 DAY),
(@noah, (SELECT id FROM lessons WHERE title = 'Ownership and borrowing'), 'Completed', 82, 'Ownership example compiled on the first try.', NOW() - INTERVAL 9 DAY, NOW() - INTERVAL 9 DAY),
(@noah, (SELECT id FROM lessons WHERE title = 'Handling HTML forms'), 'Completed', 74, 'Form works, now adding validation.', NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY),
(@noah, (SELECT id FROM lessons WHERE title = 'PHP meets MySQL'), 'In progress', 60, 'Writing the prepared insert query.', NULL, NOW() - INTERVAL 1 DAY);

-- ============================================================
-- Sample contact messages (visible in the admin inbox)
-- ============================================================

INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES
('Priya Nair', 'priya.nair@example.com', 'Group study plan',
 'Hi CodeLearn team, I run a small study group of six beginners and we would like to follow the same lesson order every week. Is there a way to save a shared learning plan? Thanks!',
 NOW() - INTERVAL 6 DAY),
('Tom Becker', 'tom.becker@example.com', 'Broken code example',
 'The Rust ownership example prints the title but I get a borrow error when I move the value after calling measure. Could the lesson mention that the reference must be dropped first?',
 NOW() - INTERVAL 3 DAY),
('Aisha Bello', 'aisha.bello@example.com', 'Adding a new language',
 'I would love to see Zig added to the language list, especially a short lesson on its error handling. Keep up the great work on the platform.',
 NOW() - INTERVAL 20 HOUR);
