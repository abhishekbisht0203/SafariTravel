<?php
/**
 * The Safari Travel image manifest.
 *
 * Every entry declares one photograph the site needs, where the file lives,
 * the intrinsic size the layout renders it at, and — critically — what the
 * photograph has to actually be about.
 *
 * The `require`, `require_title` and `exclude` clauses exist because keyword
 * search alone is not trustworthy. Openverse will answer "safari tented camp"
 * with a photograph of an insect, "tented camp" with elephants on a road, and
 * "elephant portrait" with an eighteenth-century engraving. Each clause is a
 * hard contract checked against the source file's own metadata, so a slot is
 * either filled with the right photograph or reported as empty and left for a
 * human to judge. A deliberate gap is always better than a plausible-looking
 * wrong image.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

/**
 * Manifest of the theme's photography.
 */
final class ImageManifest {

	/**
	 * Photographs, in acquisition order.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		return [
			/* ---------------------------------------------------------- hero */
			[
				'id'      => 'hero-savanna-dusk',
				'dir'     => 'hero',
				'width'   => 2400,
				'height'  => 1350,
				'aspect'  => 'wide',
				'purpose' => 'Homepage hero background',
				'require' => [
					['sunset', 'sunrise', 'dusk', 'dawn', 'golden'],
					['savanna', 'savannah', 'serengeti', 'mara', 'kruger', 'amboseli', 'africa', 'plain', 'plains', 'landscape'],
				],
				'queries' => ['serengeti sunset', 'savanna sunset', 'Kenya sunset landscape', 'African sunset acacia'],
			],
			[
				'id'      => 'hero-mara-plains',
				'dir'     => 'hero',
				'width'   => 2400,
				'height'  => 1350,
				'aspect'  => 'wide',
				'purpose' => 'Homepage hero, alternate crop',
				'require' => [
					['maasai mara', 'mara', 'serengeti', 'amboseli', 'savanna', 'savannah', 'wildebeest', 'plain', 'plains'],
				],
				'queries' => ['Maasai Mara landscape', 'Maasai Mara plains', 'Serengeti landscape plains', 'wildebeest plains Kenya'],
			],
			[
				'id'      => 'hero-destination',
				'dir'     => 'hero',
				'width'   => 2400,
				'height'  => 1200,
				'aspect'  => 'wide',
				'purpose' => 'Fallback hero for inner pages',
				'require' => [
					['elephant', 'elephants'],
					['kilimanjaro', 'amboseli', 'savanna', 'savannah', 'tsavo', 'africa'],
				],
				'queries' => ['Amboseli elephants Kilimanjaro', 'Kilimanjaro elephants', 'African elephants savanna landscape'],
			],

			/* ------------------------------------------------- destinations */
			[
				'id'      => 'maasai-mara',
				'dir'     => 'destinations',
				'width'   => 2000,
				'height'  => 1250,
				'aspect'  => 'wide',
				'purpose' => 'Maasai Mara destination',
				'require' => [
					['mara', 'kenya'],
					['landscape', 'plain', 'plains', 'savanna', 'savannah', 'wildebeest', 'grassland', 'reserve', 'national park', 'herd'],
				],
				'queries' => ['Maasai Mara landscape', 'Maasai Mara National Reserve', 'Maasai Mara wildebeest', 'Mara Kenya grassland'],
			],
			[
				'id'      => 'serengeti',
				'dir'     => 'destinations',
				'width'   => 2000,
				'height'  => 1250,
				'aspect'  => 'wide',
				'purpose' => 'Serengeti destination',
				'require' => [
					['serengeti'],
					['landscape', 'plain', 'plains', 'savanna', 'savannah', 'wildebeest', 'acacia', 'grassland', 'herd', 'valley'],
				],
				'queries' => ['Serengeti landscape', 'Serengeti plains', 'Serengeti National Park landscape', 'Serengeti acacia'],
			],
			[
				'id'      => 'amboseli',
				'dir'     => 'destinations',
				'width'   => 2000,
				'height'  => 1250,
				'aspect'  => 'wide',
				'purpose' => 'Amboseli destination',
				'require' => [
					['elephant', 'elephants', 'amboseli'],
					['kilimanjaro', 'Amboseli', 'kenya', 'savanna', 'savannah', 'swamp'],
				],
				'queries' => ['Amboseli Kilimanjaro', 'Amboseli National Park', 'elephants Kilimanjaro Amboseli', 'Amboseli Kenya landscape'],
			],
			[
				'id'      => 'okavango-delta',
				'dir'     => 'destinations',
				'width'   => 2000,
				'height'  => 1250,
				'aspect'  => 'wide',
				'purpose' => 'Okavango Delta destination',
				'require' => [
					['okavango', 'delta', 'botswana'],
				],
				'queries' => ['Okavango Delta', 'Okavango Botswana', 'Okavango Delta aerial', 'Okavango Delta water'],
			],
			[
				'id'     => 'greater-kruger',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Greater Kruger destination',
				'require' => [
					['kruger'],
					['landscape', 'plain', 'plains', 'savanna', 'savannah', 'elephant', 'leopard', 'lion', 'bushveld', 'national park', 'reserve'],
				],
				'queries' => ['Kruger National Park landscape', 'Kruger National Park elephant', 'Kruger bushveld landscape', 'Kruger South Africa plains'],
			],
			[
				'id'     => 'bwindi-forest',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Bwindi Impenetrable Forest destination',
				'require' => [
					['gorilla', 'gorillas', 'bwindi', 'virunga'],
					['forest', 'rainforest', 'jungle', 'mountain', 'uganda'],
				],
				'queries' => ['mountain gorilla Bwindi', 'Bwindi Impenetrable Forest', 'mountain gorilla Uganda forest', 'Virunga gorilla forest'],
			],
			[
				'id'     => 'etosha-namibia',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Etosha, Namibia destination',
				'require' => [
					['etosha', 'namibia', 'sossusvlei', 'namib'],
					// Without this second group the search happily answers with a
					// hornbill photographed in Etosha: correct park, wrong subject
					// for a destination that has to read as landscape.
					['landscape', 'plain', 'plains', 'savanna', 'savannah', 'desert', 'dune', 'dunes', 'waterhole', 'pan', 'reserve', 'national park', 'elephant', 'giraffe', 'zebra', 'oryx', 'springbok', 'wildebeest', 'lion', 'cheetah', 'rhino', 'leopard'],
				],
				'queries' => ['Etosha National Park landscape', 'Etosha Namibia waterhole', 'Namibia desert landscape Sossusvlei', 'Etosha plains Namibia'],
			],
			[
				'id'     => 'zambezi-victoria-falls',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Zambezi / Victoria Falls destination',
				'require' => [
					['zambezi', 'victoria falls', 'livingstone', 'zimbabwe', 'zambia'],
				],
				'queries' => ['Victoria Falls Zambezi', 'Zambezi River Zimbabwe', 'Victoria Falls aerial', 'Zambezi river Africa'],
			],
			[
				'id'     => 'akagera-rwanda',
				'dir'    => 'destinations',
				'width'  => 2000,
				'height' => 1250,
				'aspect' => 'wide',
				'purpose' => 'Akagera, Rwanda destination',
				'require' => [
					['akagera', 'rwanda'],
				],
				'queries' => ['Akagera National Park', 'Rwanda national park landscape', 'Rwanda hills landscape', 'Akagera Rwanda'],
			],

			/* --------------------------------------------------------- tours */
			[
				'id'     => 'tour-mara-crossing',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Mara river crossing itinerary',
				'require' => [
					['wildebeest', 'gnu', 'migration', 'zebra', 'herd'],
					['mara', 'river', 'crossing', 'serengeti', 'plains', 'savanna', 'savannah', 'kenya', 'tanzania'],
				],
				'queries' => ['wildebeest migration Mara river', 'Mara river wildebeest crossing', 'wildebeest migration Kenya plains'],
			],
			[
				'id'     => 'tour-serengeti-calving',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Serengeti itinerary',
				'require' => [
					['wildebeest', 'calving', 'calf', 'calves', 'herd', 'migration', 'gnus', 'gnu'],
					['serengeti', 'plains', 'savanna', 'savannah', 'tanzania', 'grassland'],
				],
				'queries' => ['Serengeti wildebeest', 'Serengeti plains herd', 'wildebeest calving Serengeti', 'Serengeti grassland'],
			],
			[
				'id'     => 'tour-amboseli-elephants',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Amboseli itinerary',
				'require' => [
					['elephant', 'elephants', 'herd'],
					['amboseli', 'kilimanjaro', 'savanna', 'savannah', 'kenya', 'africa', 'bull'],
				],
				'queries' => ['African elephant herd Amboseli', 'elephants Kilimanjaro', 'elephant family savanna Kenya', 'African elephant herd Africa'],
			],
			[
				'id'     => 'tour-okavango-water',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Okavango water itinerary',
				'require' => [
					['mokoro', 'okavango', 'delta', 'papyrus', 'canoe', 'boat', 'poles', 'poler'],
				],
				'queries' => ['Okavango mokoro', 'Okavango Delta papyrus', 'Okavango canoe poler', 'Okavango Delta water channels'],
			],
			[
				'id'     => 'tour-kruger-leopard',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Kruger itinerary',
				'require' => [
					['leopard', 'leopards', 'panthera pardus'],
				],
				'queries' => ['leopard Kruger National Park', 'leopard Africa tree', 'leopard Panthera pardus wild'],
			],
			[
				'id'     => 'tour-bwindi-gorilla',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Bwindi gorilla itinerary',
				'require' => [
					['gorilla', 'gorillas'],
					['mountain', 'bwindi', 'uganda', 'forest', 'virunga', 'ape', 'western lowland'],
				],
				'queries' => ['mountain gorilla Uganda', 'gorilla Bwindi forest', 'mountain gorilla Virunga', 'gorilla portrait'],
			],
			[
				'id'     => 'tour-namibia-desert',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Namibia itinerary',
				'require' => [
					['sossusvlei', 'deadvlei', 'namib', 'namibia', 'dune', 'dunes', 'desert', 'skeleton coast'],
				],
				'queries' => ['Sossusvlei dunes', 'Namibia desert dunes', 'Deadvlei Namibia', 'Namib desert landscape'],
			],
			[
				'id'     => 'tour-zambezi-river',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Zambezi itinerary',
				'require' => [
					['zambezi', 'victoria falls', 'canoe', 'kayak', 'rafting', 'river'],
				],
				'queries' => ['Zambezi river sunset', 'Victoria Falls Zimbabwe', 'Zambezi canoe Africa', 'Zambezi river boats'],
			],
			[
				'id'     => 'tour-lion-pride',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Big cat itinerary',
				'require' => [
					['lion', 'lions', 'panthera leo'],
				],
				'queries' => ['lion Serengeti male', 'lion pride savanna', 'African lion wildlife'],
			],
			[
				'id'     => 'tour-cheetah-plains',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Cheetah itinerary',
				'require' => [
					['cheetah', 'cheetahs', 'acinonyx'],
				],
				'queries' => ['cheetah Serengeti', 'cheetah Africa plains', 'cheetah hunting savanna'],
			],
			[
				'id'     => 'tour-rhino-conservancy',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Rhino itinerary',
				'require' => [
					['rhinoceros', 'rhino', 'rhinos', 'ceratotherium', 'diceros'],
				],
				'queries' => ['white rhinoceros South Africa', 'black rhinoceros Kenya', 'African rhinoceros wildlife'],
			],
			[
				'id'     => 'tour-zebra-herd',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Zebra itinerary',
				'require_title' => [
					['zebra', 'zebras', 'equus'],
				],
				'queries' => ['zebra herd plains', 'zebras grazing savanna', 'plains zebra Masai Mara', 'zebra Equus Africa'],
			],
			[
				'id'     => 'tour-balloon-safari',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'any',
				'purpose' => 'Balloon safari itinerary',
				'require_title' => [
					['balloon', 'balloons', 'ballooning'],
				],
				'queries' => ['hot air balloon Africa', 'hot air balloon savanna', 'balloons over Serengeti', 'hot air balloon Kenya'],
			],
			[
				'id'     => 'tour-tented-camp',
				'dir'    => 'tours',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Camp itinerary',
				'require' => [
					['camp', 'camps', 'lodge', 'lodges', 'tent', 'tents', 'tented', 'accommodation', 'chalet'],
					['safari', 'africa', 'kenya', 'tanzania', 'botswana', 'namibia', 'bush', 'reserve', 'national park'],
				],
				'queries' => ['safari tented camp Kenya', 'safari lodge Tanzania', 'tented camp Africa safari', 'safari camp Masai Mara'],
			],

			/* -------------------------------------------------------- guides */
			[
				'id'     => 'guide-packing',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'What to pack guide',
				// A literal "luggage" search on Wikimedia returns school corridors
				// and airport departures, which is worse than no image at all.
				// Binoculars are the single most-recommended item on any safari
				// packing list, are genuinely available as clean photography,
				// and say "safari" at a glance.
				'require_title' => [
					['binocular', 'binoculars', 'field glass', 'field glasses', 'rangefinder'],
				],
				'queries' => ['binoculars birdwatching', 'binoculars field', 'binoculars nature watching', 'binocular birding Africa'],
			],
			[
				'id'     => 'guide-photography',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Wildlife photography guide',
				'require_title' => [
					['elephant', 'lion', 'leopard', 'cheetah', 'giraffe', 'zebra', 'wildebeest', 'rhino', 'rhinoceros', 'buffalo', 'hippopotamus'],
				],
				'queries' => ['elephant Amboseli', 'lion Serengeti', 'leopard Kruger', 'giraffe Maasai Mara'],
			],
			[
				'id'     => 'guide-visas',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Visa and travel documentation guide',
				// A map is the honest illustration for a visa guide: the answer
				// to "what do I actually need" is always "which countries, and
				// in what order". Historical passports and visa-desk
				// photographs both read as archive material on a live planning
				// page.
				'require_title' => [
					['map', 'maps'],
				],
				'require' => [
					['map', 'maps'],
					['africa', 'kenya', 'tanzania', 'uganda', 'rwanda', 'botswana', 'zambia', 'zimbabwe', 'namibia', 'east', 'southern', 'region', 'regions', 'country', 'countries'],
				],
				// "map" alone returns population-density heat maps and war maps;
				// the queries name the region so the result is a political or
				// regional map a reader can actually navigate by.
				'exclude' => [
					['population', 'war', 'wwi', 'world war', 'climate', 'kpppen', 'wind', 'density', 'distribution', 'facies', 'tectonic', 'stress', 'routing', 'topographic'],
				],
				'queries' => ['East Africa regions map', 'Kenya regions map', 'Tanzania regions map', 'Africa political map countries'],
			],
			[
				'id'     => 'guide-birds',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Birding guide',
				'require' => [
					['bird', 'birds'],
					['africa', 'african', 'roller', 'hornbill', 'eagle', 'heron', 'kingfisher', 'southern', 'grey', 'yellow'],
				],
				'queries' => ['African bird perched', 'lilac roller bird', 'African eagle bird', 'southern ground hornbill'],
			],
			[
				'id'     => 'guide-giraffe',
				'dir'    => 'guides',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Migration guide',
				'require' => [
					['giraffe', 'giraffes', 'giraffidae', 'masai giraffe'],
				],
				'queries' => ['Masai giraffe Kenya', 'giraffe Maasai Mara', 'African giraffe savanna'],
			],

			/* -------------------------------------------------------- events */
			[
				'id'     => 'event-migration',
				'dir'    => 'events',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Migration event card',
				'require' => [
					['wildebeest', 'gnu', 'migration', 'herd'],
					['serengeti', 'mara', 'tanzania', 'kenya', 'savanna', 'savannah', 'plains', 'acacia'],
				],
				'queries' => ['wildebeest migration Serengeti', 'Serengeti wildebeest herd', 'wildebeest migration Kenya acacia'],
			],
			[
				'id'     => 'event-gorilla-departure',
				'dir'    => 'events',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Gorilla departure event card',
				'require' => [
					['gorilla', 'gorillas'],
					['mountain', 'forest', 'bwindi', 'uganda', 'virunga', 'rainforest', 'jungle'],
				],
				'queries' => ['mountain gorilla forest', 'gorilla Bwindi', 'mountain gorilla Uganda', 'gorilla rainforest'],
			],
			[
				'id'     => 'event-shoulder-season',
				'dir'    => 'events',
				'width'  => 1600,
				'height' => 1000,
				'aspect' => 'wide',
				'purpose' => 'Shoulder season event card',
				'require' => [
					['flamingo', 'flamingos', 'nakuru', 'rift valley', 'lake', 'lakes'],
				],
				'queries' => ['Lake Nakuru flamingos', 'Rift Valley Kenya lake', 'greater flamingo lake Africa', 'Kenya lake landscape'],
			],

			/* -------------------------------------------------- backgrounds */
			[
				'id'     => 'bg-dust-road',
				'dir'    => 'backgrounds',
				'width'  => 2000,
				'height' => 1125,
				'aspect' => 'wide',
				'purpose' => 'Section background',
				'require' => [
					['road', 'track', 'trail', 'path'],
					['savanna', 'savannah', 'bush', 'africa', 'kenya', 'tanzania', 'namibia', 'landscape', 'plain', 'plains'],
				],
				'queries' => ['Kenya dirt road savanna', 'African bush road landscape', 'savanna track Kenya', 'Namibia gravel road desert'],
			],
			[
				'id'     => 'bg-acacia-silhouette',
				'dir'    => 'backgrounds',
				'width'  => 2000,
				'height' => 1125,
				'aspect' => 'wide',
				'purpose' => 'Section background',
				'require' => [
					['acacia', 'tree', 'trees'],
					['sunset', 'silhouette', 'savanna', 'savannah', 'africa', 'kenya', 'samburu', 'landscape'],
				],
				'queries' => ['acacia tree sunset Kenya', 'acacia silhouette savanna', 'acacia tree Samburu', 'acacia tree Africa landscape'],
			],

			/* -------------------------------------------------- auth pages */
			[
				'id'     => 'auth-login',
				'dir'    => 'auth',
				'width'  => 1600,
				'height' => 2000,
				'aspect' => 'any',
				'purpose' => 'Login page artwork',
				'require_title' => [
					['giraffe', 'giraffes'],
				],
				'exclude' => [
					['engraving', 'lithograph', 'drawing', 'illustration', 'painting', 'sketch', 'plate', 'antique', 'museum', 'skeleton', 'drawing'],
				],
				'queries' => ['giraffe portrait', 'giraffe head', 'Masai giraffe', 'giraffe Masai Mara'],
			],
			[
				'id'     => 'auth-signup',
				'dir'    => 'auth',
				'width'  => 1600,
				'height' => 2000,
				'aspect' => 'any',
				'purpose' => 'Signup page artwork',
				'require_title' => [
					['elephant', 'elephants'],
				],
				// "Elephant" returns a disproportionate number of antique
				// engravings and anatomical plates. They are beautiful and
				// completely wrong for a sign-up screen.
				'exclude' => [
					['engraving', 'lithograph', 'drawing', 'illustration', 'painting', 'sketch', 'plate', 'antique', 'museum', 'anatomy', 'anatomical', 'cuvier', '17', '18', '19'],
				],
				'queries' => ['elephant Amboseli', 'African elephant herd', 'elephant savanna Kenya', 'elephant Loxodonta Africa'],
			],
		];
	}
}
