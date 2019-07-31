<?php
/**
 * Nonsentences
 *
 * V1.3 - 2015-Jun-24
 *
 * A nonsense sentence and title generator.
 * Copyright 2015 Chris Mospaw
 *
 * Original code based on Nonsense Generator 2.0.3
 * http://www.jholman.com/scripts/nonsense/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2.1
 * of the License, or (at your option) any later version.
 *
 * See LICENSE.md for the terms of the GNU GPL.
 */
class Nonsentences {

	protected $lists = array( 'interjections', 'determiners', 'adjectives', 'nouns', 'adverbs', 'verbs', 'prepositions', 'conjunctions', 'comparatives', 'names-first', 'names-last' );
	protected $wordlists = array();
	protected $punctuation = array( ',', '.', '?', '!', ';', ':' );
	protected $sentence_structures;
	protected $title_structures;

	public $min_sentences = 2;
	public $max_sentences = 12;
	public $min_paragraphs = 1;
	public $max_paragraphs = 5;
	public $paragraph_wrapper = array ( '<p>', '</p>' );

	public function __construct( $args = array() ) {

		if ( isset ( $args['min_sentences'] ) ) {
			$this->min_sentences = $args['min_sentences'];
		}

		if ( isset ( $args['max_sentences'] ) ) {
			$this->max_sentences = $args['max_sentences'];
		}

		if ( isset ( $args['min_paragraphs'] ) ) {
			$this->min_paragraphs = $args['min_paragraphs'];
		}

		if ( isset ( $args['max_paragraphs'] ) ) {
			$this->max_paragraphs = $args['max_paragraphs'];
		}

		if ( isset ( $args['paragraph_wrapper'] ) ) {
			$this->paragraph_wrapper = $args['paragraph_wrapper'];
		}

		foreach ( $this->lists as $list ) {
			$this->wordlists[ $list ] = file( realpath( dirname( __FILE__ ) ) . '/db/' . $list . '.txt' );
		}

		// Sentence structures ... each is randomly selected. The first two are weighted since they're a bit more "normal",
		$this->sentence_structures = array(
			explode( ' ', '[determiners] [adjectives] [nouns] [verbs] [prepositions] [determiners] [nouns] .' ),
			explode( ' ', '[determiners] [adjectives] [nouns] [verbs] [prepositions] [determiners] [nouns] .' ),
			explode( ' ', '[determiners] [adjectives] [nouns] [verbs] [prepositions] [determiners] [nouns] .' ),
			explode( ' ', '[determiners] [adjectives] [nouns] [adverbs] [verbs] [prepositions] [determiners] [adjectives] [nouns] .' ),
			explode( ' ', '[determiners] [adjectives] [nouns] [adverbs] [verbs] [prepositions] [determiners] [adjectives] [nouns] .' ),
			explode( ' ', '[determiners] [adjectives] [nouns] [adverbs] [verbs] [prepositions] [determiners] [adjectives] [nouns] .' ),
			explode( ' ', '[interjections] , [determiners] [nouns] is [comparatives] [adjectives] than [determiners] [adjectives] [nouns] .' ),
			explode( ' ', '[adjectives] [plural_nouns] [verbs] [prepositions] [plural_nouns] [adverbs] .' ),
			explode( ' ', '[determiners] [nouns] , [adverbs] [verbs] the [nouns] .' ),
			explode( ' ', '[interjections] ! The [nouns] [verbs] the [nouns] .' ),
		);

		// Title structures ... each is randomly selected.
		$this->title_structures = array(
			explode( ' ', '[adjectives] [nouns]' ),
			explode( ' ', '[adverbs] [verbs] [nouns]' ),
			explode( ' ', '[adverbs] [verbs] [nouns]' ),
			explode( ' ', '[interjections] , [determiners] [nouns] [verbs]' ),
			explode( ' ', '[interjections] ! The [nouns] [verbs] the [nouns]' ),
			explode( ' ', '[adverbs] [verbs] [nouns] [conjunctions] [adjectives] [plural_nouns]' ),
			explode( ' ', '[adverbs] [verbs] [nouns] [conjunctions] [adjectives] [plural_nouns]' ),
			explode( ' ', '[determiners] [adjectives] [nouns] [verbs] [prepositions] [determiners] [nouns]' ),
			explode( ' ', '[determiners] [adjectives] [nouns] [adverbs] [verbs] [prepositions] [determiners] [adjectives] [nouns]' ),
		);
	}

	/**
	 * Generate one nonsense title.
	 */
	public function title() {
		return ucwords( $this->get_words( 'title_structures' ) );
	}

	/**
	 * Get paragraph(s) assembled from random sentences, wrapped in the
	 * configured wrapper.
	 */
	public function paragraphs( $min = 0, $max = 0 ) {

		if ( 0 === $min ) {
			$min = $this->min_paragraphs;
		}

		if ( 0 === $max ) {
			$max = $this->max_paragraphs;
		}

		$count = mt_rand( $min, $max );

		$output = '';

		for ( $i = 0; $i < $count; $i++ ) {
			$output .= $this->paragraph_wrapper[0];
			$output .= trim( $this->sentences() );
			$output .= $this->paragraph_wrapper[1];
		}

		return $output . PHP_EOL;
	}

	/**
	 * Get a number of nonsense sentences.
	 */
	public function sentences( $min = 0, $max = 0 ) {

		if ( 0 === $min ) {
			$min = $this->min_sentences;
		}

		if ( 0 === $max ) {
			$max = $this->max_sentences;
		}

		$count = mt_rand( $min, $max );

		$output = '';

		for ( $i = 0; $i < $count; $i++ ) {
			$output .= $this->sentence() . ' ';
		}

		return $output;
	}

	/**
	 * Get one nonsense sentence.
	 */
	public function sentence() {
		return $this->get_words( 'sentence_structures' );
	}

	/**
	 * Get a list of random words matching the passed structure type.
	 */
	public function get_words( $structure_type ) {
		$wordcount = array();

		$type = mt_rand( 0, ( count( $this->{ $structure_type } ) - 1 ) );

		$nouns = array();
		for ( $i = 0; $i < 2; $i++ ) {
			foreach ( $this->lists as $list ) {
				${$list}[ $i ] = $this->get_word( $list );
				$wordcount[ $list ] = 0;
			}
		}

		$sentence_structure = $this->{$structure_type}[ $type ];
		$word_list = '';

		foreach ( $sentence_structure as $position => $word ) {
			switch (1) {
				case ( 0 === $position ) :
					$word = str_replace( array( '[' , ']' ), array ( '', ''), $word );
					$word_list .= ucfirst( ${$word}[ $wordcount[ $word ] ] );
					$wordcount[ $word ]++;
					break;
				case ( '[nouns]' === $word ) :
					$word_list .= $nouns[ $wordcount['nouns'] ]['singular'];
					$wordcount['nouns']++;
					break;
				case ( '[plural_nouns]' === $word ) :
					$word_list .= $nouns[ $wordcount['nouns'] ]['plural'];
					$wordcount['nouns']++;
					break;
				case ( '[' === substr( $word, 0, 1 ) ) :
					$word = str_replace( array( '[' , ']' ), array ( '', '' ), $word );
					$word_list .= ${$word}[ $wordcount[ $word ] ];
					$wordcount[ $word ]++;
					break;
				case ( ! in_array( $word, $this->punctuation) ) :
					$word_list .= $word;
					break;
			}

			if ( in_array( $word, $this->punctuation ) ) {
				$word_list = trim( $word_list ) . $word;
				if ( '.' !== $word ) {
					$word_list .= ' ';
				}
			} else {
				$word_list .= ' ';
			}

		}

		return $word_list;
	}

	/**
	 * Get a random word from the given word list.
	 */
	public function get_word( $list = 'nouns' ) {

		if ( ! in_array( $list, $this->lists ) ) {
			$list = 'nouns';
		}

		$word = $word = trim( $this->wordlists[ $list ][ mt_rand( 0, count( $this->wordlists[ $list ] ) - 1 ) ] );

		if ( 'nouns' === $list ) {
			$split_noun = explode( '|', $word );
			$word = array( 'singular' => $split_noun[0], 'plural' => $split_noun[1] );
		}

		return $word;
	}
}
