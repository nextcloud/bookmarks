#!/usr/bin/perl
use strict;
use Locale::PO;
use Cwd;
use Data::Dumper;
use File::Path;
use File::Basename;

sub crawlFiles{
	my( $dir ) = @_;
	my @found = ();

	opendir( DIR, $dir );
	my @files = readdir( DIR );
	closedir( DIR );
	@files = sort( @files );

	foreach my $i ( @files ){
		next if substr( $i, 0, 1 ) eq '.';
		next if $i eq 'l10n';
		
		if( -d $dir.'/'.$i ){
			push( @found, crawlFiles( $dir.'/'.$i ));
		}
		else{
			push(@found,$dir.'/'.$i) if $i =~ /\.js$/ || $i =~ /\.php$/;
		}
	}

	return @found;
}

sub readIgnorelist{
	return () unless -e 'l10n/ignorelist';
	my %ignore = ();
	open(IN,'l10n/ignorelist');
	while(<IN>){
		my $line = $_;
		chomp($line);
		$ignore{"./$line"}++;
	}
	close(IN);
	return %ignore;
}

my $app = shift( @ARGV );
my $task = shift( @ARGV );

die( "Usage: l10n.pl app task\ntask: read, write\n" ) unless $task;

# Our current position
my $whereami = cwd();
die( "Program must be executed in a l10n-folder called 'l10n'" ) unless $whereami =~ m/\/l10n$/;

# Where are i18n-files?
my $pwd = dirname(cwd());

my @dirs = ();
push(@dirs, $pwd);

# Languages
my @languages = ();
opendir( DIR, '.' );
my @files = readdir( DIR );
closedir( DIR );
foreach my $i ( @files ){
	push( @languages, $i ) if -d $i && substr( $i, 0, 1 ) ne '.';
}

if( $task eq 'read' ){
	rmtree( 'templates' );
	mkdir( 'templates' ) unless -d 'templates';
	print "Mode: reading\n";
	foreach my $dir ( @dirs ){
		my @temp = split( /\//, $dir );
		chdir( $dir );
		my @totranslate = crawlFiles('.');
		my %ignore = readIgnorelist();
		my $output = "${whereami}/templates/$app.pot";
		my $packageName = "ownCloud $app";
		print "  Processing $app\n";

		foreach my $file ( @totranslate ){
			next if $ignore{$file};
			# TODO: add support for twig templates
			my $keyword = ( $file =~ /\.js$/ ? 't:2' : 't');
			my $language = ( $file =~ /\.js$/ ? 'Python' : 'PHP');
			my $joinexisting = ( -e $output ? '--join-existing' : '');
			print "    Reading $file\n";
			`xgettext --output="$output" $joinexisting --keyword=$keyword --language=$language "$file" --from-code=UTF-8 --package-version="5.0.0" --package-name="$packageName" --msgid-bugs-address="translations\@owncloud.org"`;
		}
		chdir( $whereami );
	}
}
elsif( $task eq 'write' ){
	print "Mode: write\n";
	foreach my $dir ( @dirs ){
		my @temp = split( /\//, $dir );
		chdir( $dir.'/l10n' );
		print "  Processing $app\n";
		foreach my $language ( @languages ){
			next if $language eq 'templates';
			
			my $input = "${whereami}/$language/$app.po";
			next unless -e $input;

			print "    Language $language\n";
			my $array = Locale::PO->load_file_asarray( $input );
			# Create array
			my @strings = ();
			foreach my $string ( @{$array} ){
				next if $string->msgid() eq '""';
				next if $string->msgstr() eq '""';
				push( @strings, $string->msgid()." => ".$string->msgstr());
			}
			next if $#strings == -1; # Skip empty files

			# Write PHP file
			open( OUT, ">$language.php" );
			print OUT "<?php \$TRANSLATIONS = array(\n";
			print OUT join( ",\n", @strings );
			print OUT "\n);\n";
			close( OUT );
		}
		chdir( $whereami );
	}
}
else{
	print "unknown task!\n";
}
