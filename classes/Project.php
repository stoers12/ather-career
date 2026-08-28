<?php

class Project
{
    private $id;
    private $title;
    private $category;
    private $description;
    private $githubUrl;
    private $imagePath;

    public function __construct(
        int $id,
        string $title,
        string $category,
        string $description,
        string $githubUrl,
        ?string $imagePath
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->category = $category;
        $this->description = $description;
        $this->githubUrl = $githubUrl;
        $this->imagePath = $imagePath;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getGithubUrl(): string
    {
        return $this->githubUrl;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }
}
