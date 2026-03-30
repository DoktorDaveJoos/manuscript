<?php

namespace App\Enums;

enum BackMatterType: string
{
    case Epilogue = 'epilogue';
    case Acknowledgments = 'acknowledgments';
    case AboutAuthor = 'about-author';
    case AlsoBy = 'also-by';
}
