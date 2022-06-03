<?php
require_once("Auth.php");

class Shows extends Auth
{
  private static $db;

  function __construct($db)
  {
    $this::$db = $db;
  }

  function authenticateUser()
  {
    // Implement User authentication here
    return true;
  }

  function fetchMovies()
  {
    $db = $this::$db;
    $response = object(["status" => false]);
    if ($this->authenticateUser()) {
      $response->status = true;
      $response->data =  $db->select("movie");
    } else $response->message = "UNAUTHORIZED_ACCESS";
    return $response;
  }


  function fetchBookings($filtered = "")
  {
    $db = $this::$db;
    $response = object(["status" => false]);
    if (!empty($filtered)) $filtered = "WHERE b.id={$filtered}";
    if ($this->authenticateUser()) {
      $sql = "SELECT m.title, b.id, b.location, b.price, b.theatre_name, b.show_date, b.show_time, b.movie_id, b.date
      FROM booking AS b
      INNER JOIN movie AS m
      ON b.movie_id=m.id 
      {$filtered}
      ORDER BY b.id DESC
      ";
      $data = $db->query($sql);
      $data = array_map(function ($x) {
        $bdate = new DateTime($x->date);
        $sdate = new DateTime("{$x->show_date} {$x->show_time}");
        $x->price = "$" . $x->price;
        $x->date = $bdate->format("Y-m-d h:i a");
        $x->formatted_show_time = $sdate->format("Y-m-d h:i a");
        return $x;
      }, $data);
      if (!empty($filtered)) $data = reset($data);
      $response = ["status" => true, "data" => $data];
    } else $response->message = "UNAUTHORIZED_ACCESS";
    return $response;
  }

  function listShows()
  {
    $db = $this::$db;
    $response = object(["status" => true]);

    $bookin = $db->select("booking");

    $sql = "SELECT m.title, m.id, m.cast, m.genre, m.language
    FROM movie AS m
    INNER JOIN booking AS b
    ON m.id = b.movie_id
    GROUP BY b.movie_id
    ORDER BY m.id DESC";
    $movies = $db->query($sql);

    $response->data = array_map(function ($movie) use ($bookin) {
      $movie->language = str_replace(",", ", ", $movie->language);
      $booked = array_map(function ($x) {
        $bdate = new DateTime("{$x->show_date} {$x->show_time}");
        $x->date = $bdate->format("Y-m-d h:ia");
        return $x;
      }, array_filter($bookin, function ($booked) use ($movie) {
        return $booked->movie_id === $movie->id;
      }));
      $movie->booked = array_group(array_values($booked), "location");
      $movie->num_location = count($movie->booked);
      return $movie;
    }, $movies);

    return $response;
  }

  function createMovie($movie_data)
  {
    $db = $this::$db;
    $response = object(["status" => false]);
    if ($this->authenticateUser()) {
      $data =  $db->select("movie", "title={$movie_data->title}");
      if (!count($data)) {
        $response =  $db->insert("movie", $movie_data);
      } else  $response->message = "DATA_EXISTS";
    } else $response->message = "UNAUTHORIZED_ACCESS";
    return $response;
  }

  function createBooking($event)
  {
    $db = $this::$db;
    $response = object(["status" => false]);
    if ($this->authenticateUser()) {
      $data =  $db->select("booking", "location={$event->location}, theatre_name={$event->theatre_name}, show_time={$event->show_time},show_date={$event->show_date}");
      if (!count($data)) {
        $response =  $db->insert("booking", $event);
        if ($response->status) {
          $response = $this->fetchBookings($response->data->id);
        }
      } else  $response->message = "DATA_EXISTS";
    } else $response->message = "UNAUTHORIZED_ACCESS";
    return $response;
  }
}
