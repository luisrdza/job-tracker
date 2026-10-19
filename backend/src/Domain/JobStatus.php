<?php
declare(strict_types=1);
namespace App\Domain;
enum JobStatus: string
{
    case DRAFT = 'DRAFT';
    case APPLIED = 'APPLIED';
    case INTERVIEWING = 'INTERVIEWING';
    case REJECTED = 'REJECTED';
    case OFFER = 'OFFER';
}